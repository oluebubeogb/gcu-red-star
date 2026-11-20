<?php
namespace App\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdSlot;
use App\Models\Business;
use App\Models\Edition;
use App\Models\Payment;
use App\Models\PrintOrder;
use App\Models\Setting;
use App\Models\Sponsorship;
use App\Models\SponsorshipPackage;
use App\Payments\Gateways;
use App\Storage\MagazineStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** HQ → Commerce: sponsorship packages + approvals, directory and ad approvals, print stock + orders, revenue. */
class CommerceController extends Controller
{
    public function __construct(private MagazineStorage $storage) {}

    private function url(?string $key): ?string { return $key ? $this->storage->url($key) : null; }

    public function index(Gateways $gateways)
    {
        return Inertia::render('Hq/Commerce', [
            'gateway' => $gateways->status(),
            'packages' => SponsorshipPackage::orderBy('sort')->orderBy('price_kobo')->get(),
            'sponsorships' => Sponsorship::with('package:id,name', 'edition:id,title')->where('status', '!=', 'pending_payment')
                ->orderByRaw("CASE status WHEN 'paid' THEN 0 ELSE 1 END")->latest()->limit(60)->get()
                ->map(fn ($s) => $s->only(['id', 'sponsor_type', 'sponsor_name', 'set_year', 'house', 'target', 'homecoming_year', 'website', 'email', 'message', 'amount_kobo', 'status'])
                    + ['package' => $s->package?->name, 'edition' => $s->edition?->title, 'logo' => $this->url($s->logo_path)]),
            'businesses' => Business::with('user:id,name,username,admission_year')->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")->latest()->limit(60)->get()
                ->map(fn ($b) => $b->card() + ['status' => $b->status, 'review_note' => $b->review_note, 'featured_until' => optional($b->featured_until)->toDateString()]),
            'ads' => Ad::orderByRaw("CASE status WHEN 'paid' THEN 0 ELSE 1 END")->where('status', '!=', 'pending_payment')->latest()->limit(60)->get()
                ->map(fn ($a) => $a->only(['id', 'slot_key', 'kind', 'advertiser', 'email', 'title', 'body', 'link_url', 'audience', 'amount_kobo', 'status', 'impressions', 'clicks', 'review_note'])
                    + ['image' => $this->url($a->image_path), 'starts_on' => $a->starts_on->toDateString(), 'ends_on' => $a->ends_on->toDateString(),
                       'ctr' => $a->impressions ? round($a->clicks / $a->impressions * 100, 2) : 0]),
            'slots' => AdSlot::orderBy('id')->get(),
            'featuredMonthKobo' => (int) Setting::get('commerce.featured_month_kobo', config('commerce.featured_month_kobo')),
            'printEditions' => Edition::orderByDesc('print_available')->latest('id')->limit(100)->get(['id', 'title', 'year', 'print_available', 'print_price_kobo', 'print_stock']),
            'orders' => PrintOrder::with('edition:id,title', 'user:id,name,email')->where('status', '!=', 'pending_payment')
                ->orderByRaw("CASE status WHEN 'paid' THEN 0 WHEN 'printing' THEN 1 ELSE 2 END")->latest()->limit(100)->get()
                ->map(fn ($o) => $o->toArray() + ['created' => $o->created_at->toDateString()]),
            'statuses' => PrintOrder::STATUSES,
        ]);
    }

    // Sponsorship packages + approvals
    public function storePackage(Request $request)
    {
        SponsorshipPackage::create($this->package($request));
        return back()->with('status', 'Package added.');
    }

    public function updatePackage(Request $request, SponsorshipPackage $package)
    {
        $package->update($this->package($request));
        return back()->with('status', 'Package saved.');
    }

    public function destroyPackage(SponsorshipPackage $package)
    {
        $package->sponsorships()->exists() ? $package->update(['active' => false]) : $package->delete();
        return back()->with('status', 'Package removed.');
    }

    private function package(Request $request): array
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'target' => ['required', 'in:any,edition,homecoming'],
            'price_naira' => ['required', 'numeric', 'min:100', 'max:100000000'], 'perks' => ['nullable', 'string', 'max:2000'],
            'active' => ['boolean'], 'sort' => ['nullable', 'integer', 'min:0', 'max:999']]);
        return ['name' => $d['name'], 'target' => $d['target'], 'price_kobo' => (int) round($d['price_naira'] * 100),
            'perks' => $d['perks'] ?? null, 'active' => $d['active'] ?? true, 'sort' => $d['sort'] ?? 0];
    }

    public function reviewSponsorship(Request $request, Sponsorship $sponsorship)
    {
        $d = $request->validate(['status' => ['required', 'in:approved,rejected,paid'], 'sponsor_name' => ['required', 'string', 'max:160'],
            'remove_logo' => ['boolean']]);
        $sponsorship->update(['status' => $d['status'], 'sponsor_name' => $d['sponsor_name'], 'reviewed_by' => $request->user()->id]
            + (! empty($d['remove_logo']) ? ['logo_path' => null] : []));
        return back()->with('status', 'Sponsorship '.$d['status'].'.');
    }

    // Directory + ads
    public function reviewBusiness(Request $request, Business $business)
    {
        $d = $request->validate(['status' => ['required', 'in:approved,rejected,pending'], 'review_note' => ['nullable', 'string', 'max:300']]);
        $business->update($d);
        return back()->with('status', 'Listing '.$d['status'].'.');
    }

    public function reviewAd(Request $request, Ad $ad)
    {
        $d = $request->validate(['status' => ['required', 'in:approved,rejected'], 'review_note' => ['nullable', 'string', 'max:300']]);
        $ad->update($d);
        return back()->with('status', 'Creative '.$d['status'].'.');
    }

    public function updateSlot(Request $request, AdSlot $slot)
    {
        $d = $request->validate(['price_naira' => ['required', 'numeric', 'min:0'], 'period_days' => ['required', 'integer', 'min:1', 'max:365'], 'active' => ['boolean']]);
        $slot->update(['price_kobo' => (int) round($d['price_naira'] * 100), 'period_days' => $d['period_days'], 'active' => $d['active'] ?? true]);
        return back()->with('status', 'Slot saved.');
    }

    public function updateFeaturedPrice(Request $request)
    {
        $d = $request->validate(['price_naira' => ['required', 'numeric', 'min:100']]);
        Setting::put('commerce.featured_month_kobo', (int) round($d['price_naira'] * 100));
        return back()->with('status', 'Featured listing price saved.');
    }

    // Print
    public function updatePrint(Request $request, Edition $edition)
    {
        $d = $request->validate(['print_available' => ['boolean'], 'price_naira' => ['nullable', 'required_if:print_available,true', 'numeric', 'min:0'],
            'print_stock' => ['required', 'integer', 'min:0', 'max:100000']]);
        $edition->forceFill(['print_available' => $d['print_available'] ?? false, 'print_stock' => $d['print_stock'],
            'print_price_kobo' => isset($d['price_naira']) ? (int) round($d['price_naira'] * 100) : $edition->print_price_kobo])->save();
        return back()->with('status', 'Print settings saved.');
    }

    public function updateOrder(Request $request, PrintOrder $order)
    {
        $d = $request->validate(['status' => ['required', Rule::in(array_diff(PrintOrder::STATUSES, ['pending_payment']))],
            'tracking_note' => ['nullable', 'string', 'max:300']]);
        abort_if($order->status === 'pending_payment', 422, 'This order has not been paid.');
        $changed = $order->status !== $d['status'];
        if ($changed && $d['status'] === 'cancelled') $order->edition()->increment('print_stock', $order->quantity); // return stock
        $order->update($d);
        $labels = ['printing' => 'Your order is being printed', 'shipped' => 'Your order has shipped', 'ready' => 'Your order is ready for pickup',
            'delivered' => 'Your order was delivered', 'cancelled' => 'Your order was cancelled'];
        if ($changed && isset($labels[$d['status']])) $order->notify($labels[$d['status']]);
        return back()->with('status', 'Order updated.');
    }

    // Revenue
    public function revenue()
    {
        $paid = Payment::successful()->where('paid_at', '>=', now()->subMonths(11)->startOfMonth())->get(['purpose', 'amount_kobo', 'paid_at']);
        $months = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        $streams = ['sponsorship', 'listing', 'ad', 'print'];
        return Inertia::render('Hq/Revenue', [
            'totals' => collect($streams)->mapWithKeys(fn ($s) => [$s => (int) Payment::successful()->where('purpose', $s)->sum('amount_kobo')]),
            'months' => $months->map(fn ($m) => ['month' => $m] + collect($streams)->mapWithKeys(fn ($s) => [$s => (int) $paid
                ->filter(fn ($p) => $p->purpose === $s && $p->paid_at->format('Y-m') === $m)->sum('amount_kobo')])->all())->values(),
            'recent' => Payment::with('user:id,name,username')->latest()->limit(30)->get()
                ->map(fn ($p) => $p->only(['id', 'reference', 'email', 'purpose', 'amount_kobo', 'status', 'channel', 'gateway']) + [
                    'paid_at' => optional($p->paid_at)->toDateTimeString(), 'created_at' => $p->created_at->toDateTimeString(), 'user' => $p->user?->name]),
        ]);
    }

    public function export()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['reference', 'paid_at', 'gateway', 'purpose', 'amount_naira', 'currency', 'email', 'member', 'channel', 'provider_id']);
            Payment::successful()->with('user:id,username')->orderBy('paid_at')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) fputcsv($out, [$p->reference, $p->paid_at?->toDateTimeString(), $p->gateway ?: 'paystack', $p->purpose,
                    number_format($p->amount_kobo / 100, 2, '.', ''), $p->currency, $p->email, $p->user?->username, $p->channel, $p->paystack_id]);
            });
            fclose($out);
        }, 'red-star-revenue-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }
}
