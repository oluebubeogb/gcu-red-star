<?php
namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\AdSlot;
use App\Models\Business;
use App\Models\Edition;
use App\Models\PrintOrder;
use App\Models\Setting;
use App\Models\Sponsorship;
use App\Models\SponsorshipPackage;
use App\Payments\PaymentException;
use App\Payments\Payments;
use App\Storage\MagazineStorage;
use App\Support\Homecoming;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Public/member side of Phase 6: sponsorships, business directory, ads & notices, printed copies. */
class CommerceController extends Controller
{
    public function __construct(private Payments $payments, private MagazineStorage $storage) {}

    private function checkout($model, string $purpose, int $kobo, string $email, Request $request, array $meta = [])
    {
        try {
            $url = $this->payments->start($model, $purpose, $kobo, $email, $request->user(), $meta);
            return Inertia::location($url);
        } catch (PaymentException $e) {
            Log::warning('redstar pay: start failed', ['purpose' => $purpose, 'error' => $e->getMessage()]);
            return back()->withErrors(['payment' => 'Online payment is unavailable right now. Please try again later or contact the editors.']);
        }
    }

    private function needPayments(): void
    {
        abort_unless($this->payments->enabled(), 503, 'Online payments are not available right now.');
    }

    private function upload(Request $request, string $field, string $dir): ?string
    {
        return $request->hasFile($field) ? $this->storage->putFile("commerce/$dir", $request->file($field)) : null;
    }

    // ── Sponsorships ───────────────────────────────────────────────────
    public function sponsor()
    {
        $year = Homecoming::upcomingSet() + Homecoming::YEARS;
        return Inertia::render('Public/Sponsor', [
            'packages' => SponsorshipPackage::where('active', true)->orderBy('sort')->orderBy('price_kobo')->get(['id', 'name', 'target', 'price_kobo', 'perks']),
            'editions' => Edition::published()->latest('published_at')->limit(30)->get(['id', 'title', 'year']), // drafts stay private
            'houses' => Roles::HOUSES, 'homecomingYear' => $year,
            'sponsors' => Sponsorship::credited()->with('package:id,name')->latest('paid_at')->limit(40)->get()->map->credit(),
        ]);
    }

    public function storeSponsor(Request $request)
    {
        $this->needPayments();
        $data = $request->validate([
            'package_id' => ['required', Rule::exists('sponsorship_packages', 'id')->where('active', true)],
            'sponsor_type' => ['required', 'in:set,house,individual,company'],
            'sponsor_name' => ['required', 'string', 'max:160'],
            'set_year' => ['nullable', 'required_if:sponsor_type,set', 'integer', 'min:1927', 'max:'.now()->year],
            'house' => ['nullable', 'required_if:sponsor_type,house', Rule::in(Roles::HOUSES)],
            'target' => ['required', 'in:edition,homecoming'],
            'edition_id' => ['nullable', 'required_if:target,edition', 'exists:editions,id'],
            'email' => ['required', 'email', 'max:190'],
            'website' => ['nullable', 'url', 'max:255'],
            'message' => ['nullable', 'string', 'max:300'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        $package = SponsorshipPackage::findOrFail($data['package_id']);
        if ($package->target !== 'any' && $package->target !== $data['target']) {
            return back()->withErrors(['package_id' => 'That package is for '.$package->target.' sponsorship.']);
        }
        $s = Sponsorship::create(collect($data)->except('logo')->all() + [
            'user_id' => $request->user()?->id, 'amount_kobo' => $package->price_kobo,
            'homecoming_year' => $data['target'] === 'homecoming' ? Homecoming::upcomingSet() + Homecoming::YEARS : null,
            'logo_path' => $this->upload($request, 'logo', 'sponsors'),
        ]);
        return $this->checkout($s, 'sponsorship', $package->price_kobo, $data['email'], $request);
    }

    // ── Business directory ─────────────────────────────────────────────
    public function directory(Request $request)
    {
        $f = $request->only(['q', 'category', 'state']);
        $q = Business::approved()->with('user:id,name,username,admission_year,show_in_directory')
            ->when($f['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($f['state'] ?? null, fn ($q, $s) => $q->where('state', $s))
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('description', 'like', "%$t%")))
            ->orderByRaw('CASE WHEN featured_until > ? THEN 0 ELSE 1 END', [now()])->orderBy('name');
        return Inertia::render('Public/Directory', [
            'businesses' => $q->paginate(24)->withQueryString()->through(fn ($b) => $b->card()),
            'filters' => $f, 'categories' => config('commerce.business_categories'), 'states' => config('commerce.states'),
        ]);
    }

    public function myBusinesses(Request $request)
    {
        return Inertia::render('Member/Businesses', [
            'businesses' => $request->user()->businesses()->latest()->get()->map(fn ($b) => $b->card() + [
                'status' => $b->status, 'review_note' => $b->review_note, 'featured_until' => optional($b->featured_until)->toDateString()]),
            'categories' => config('commerce.business_categories'), 'states' => config('commerce.states'),
            'featuredMonthKobo' => (int) Setting::get('commerce.featured_month_kobo', config('commerce.featured_month_kobo')),
        ]);
    }

    private function businessRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(config('commerce.business_categories'))],
            'description' => ['required', 'string', 'max:2000'],
            'state' => ['required', Rule::in(config('commerce.states'))],
            'location' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function storeBusiness(Request $request)
    {
        abort_if($request->user()->businesses()->count() >= 5, 422, 'You can list up to 5 businesses.');
        $data = $request->validate($this->businessRules());
        $slug = Str::slug($data['name']) ?: 'business';
        while (Business::where('slug', $slug)->exists()) $slug = Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        $request->user()->businesses()->create(collect($data)->except('logo')->all() + [
            'slug' => $slug, 'status' => 'pending', 'logo_path' => $this->upload($request, 'logo', 'businesses')]);
        return back()->with('status', 'Listing submitted — the editors will review it before it goes public.');
    }

    public function updateBusiness(Request $request, Business $business)
    {
        abort_unless($business->user_id === $request->user()->id, 403);
        $data = $request->validate($this->businessRules());
        $logo = $this->upload($request, 'logo', 'businesses');
        // Any edit goes back to review so approved listings can't be changed unseen.
        $business->update(collect($data)->except('logo')->all() + ['status' => 'pending'] + ($logo ? ['logo_path' => $logo] : []));
        return back()->with('status', 'Listing updated — it will be visible again once re-approved.');
    }

    public function destroyBusiness(Request $request, Business $business)
    {
        abort_unless($business->user_id === $request->user()->id, 403);
        $business->delete();
        return back()->with('status', 'Listing removed.');
    }

    public function featureBusiness(Request $request, Business $business)
    {
        $this->needPayments();
        abort_unless($business->user_id === $request->user()->id, 403);
        $months = (int) $request->validate(['months' => ['required', 'integer', 'in:1,3,6,12']])['months'];
        $kobo = $months * (int) Setting::get('commerce.featured_month_kobo', config('commerce.featured_month_kobo'));
        return $this->checkout($business, 'listing', $kobo, $request->user()->email, $request, ['months' => $months]);
    }

    // ── Ads & notices ──────────────────────────────────────────────────
    public function advertise(Request $request)
    {
        return Inertia::render('Public/Advertise', [
            'slots' => AdSlot::where('active', true)->orderBy('price_kobo', 'desc')->get(['key', 'name', 'kind', 'price_kobo', 'period_days', 'size_hint']),
            'mine' => $request->user() ? Ad::where('user_id', $request->user()->id)->latest()->limit(20)->get()
                ->map(fn ($a) => $a->only(['id', 'title', 'slot_key', 'status', 'impressions', 'clicks', 'review_note']) + [
                    'starts_on' => $a->starts_on->toDateString(), 'ends_on' => $a->ends_on->toDateString()]) : [],
        ]);
    }

    public function storeAd(Request $request)
    {
        $this->needPayments();
        $data = $request->validate([
            'slot_key' => ['required', Rule::exists('ad_slots', 'key')->where('active', true)],
            'kind' => ['required', 'in:ad,congratulatory,obituary'],
            'advertiser' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190'],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:1000'],
            'link_url' => ['nullable', 'url', 'max:255'],
            'audience' => ['required', 'in:all,guests,members'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'periods' => ['required', 'integer', 'min:1', 'max:12'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);
        $slot = AdSlot::where('key', $data['slot_key'])->firstOrFail();
        if (($slot->kind === 'notice') !== ($data['kind'] !== 'ad')) {
            return back()->withErrors(['kind' => $slot->kind === 'notice' ? 'Choose congratulatory or obituary for notices.' : 'Notices go in the notices slot.']);
        }
        $start = \Illuminate\Support\Carbon::parse($data['starts_on']);
        $ad = Ad::create(collect($data)->except('image')->all() + [
            'user_id' => $request->user()?->id, 'ends_on' => $start->copy()->addDays($slot->period_days * $data['periods'] - 1),
            'amount_kobo' => $slot->price_kobo * $data['periods'], 'image_path' => $this->upload($request, 'image', 'ads'),
        ]);
        return $this->checkout($ad, 'ad', $ad->amount_kobo, $data['email'], $request);
    }

    public function clickAd(Ad $ad)
    {
        abort_unless($ad->link_url && Ad::live()->whereKey($ad->id)->exists(), 404);
        $ad->increment('clicks');
        return redirect()->away($ad->link_url);
    }

    // ── Printed copies ─────────────────────────────────────────────────
    public function printForm(Request $request, Edition $edition)
    {
        abort_unless($edition->print_available, 404);
        $u = $request->user();
        return Inertia::render('Member/PrintOrder', [
            'edition' => $edition->only(['id', 'title', 'slug', 'year', 'print_price_kobo', 'print_stock']) + ['cover_url' => $edition->coverUrl()],
            'states' => array_values(array_diff(config('commerce.states'), ['Outside Nigeria'])),
            'defaults' => ['name' => $u->name, 'phone' => ''],
        ]);
    }

    public function storePrint(Request $request, Edition $edition)
    {
        $this->needPayments();
        abort_unless($edition->print_available && $edition->print_price_kobo, 404);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.max(1, min(20, $edition->print_stock))],
            'fulfilment' => ['required', 'in:delivery,pickup'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required_if:fulfilment,delivery', 'nullable', 'string', 'max:255'],
            'city' => ['required_if:fulfilment,delivery', 'nullable', 'string', 'max:80'],
            'state' => ['required_if:fulfilment,delivery', 'nullable', Rule::in(config('commerce.states'))],
        ]);
        if ($edition->print_stock < $data['quantity']) return back()->withErrors(['quantity' => 'Only '.$edition->print_stock.' copies left.']);
        $order = PrintOrder::create($data + ['user_id' => $request->user()->id, 'edition_id' => $edition->id,
            'unit_kobo' => $edition->print_price_kobo, 'amount_kobo' => $edition->print_price_kobo * $data['quantity']]);
        return $this->checkout($order, 'print', $order->amount_kobo, $request->user()->email, $request);
    }

    public function orders(Request $request)
    {
        return Inertia::render('Member/Orders', [
            'orders' => PrintOrder::with('edition:id,title,slug,year')->where('user_id', $request->user()->id)->latest()->get()
                ->map(fn ($o) => $o->only(['id', 'quantity', 'amount_kobo', 'fulfilment', 'status', 'tracking_note', 'state']) + [
                    'edition' => $o->edition?->only(['title', 'slug', 'year']), 'created_at' => $o->created_at->toDateString()]),
        ]);
    }
}
