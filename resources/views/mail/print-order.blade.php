<x-mail::message>
# {{ $headline }}

Hello {{ $o->name }}, here are the details of your printed copy order **#{{ $o->id }}**.

<x-mail::table>
| Item | Qty | Amount |
|:-----|:---:|-------:|
| {{ $o->edition->title }}{{ $o->edition->year ? ' ('.$o->edition->year.')' : '' }} | {{ $o->quantity }} | ₦{{ number_format($o->amount_kobo / 100, 2) }} |
</x-mail::table>

@php
    $status = ['paid' => 'Paid — awaiting printing', 'printing' => 'Printing', 'shipped' => 'Shipped', 'ready' => 'Ready for pickup at the school', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'][$o->status] ?? $o->status;
@endphp
**Status:** {{ $status }}

@if($o->fulfilment === 'pickup')
**Pickup:** at Government College Umuahia — we will email you when it is ready.
@else
**Deliver to:** {{ $o->address }}, {{ $o->city }}, {{ $o->state }} State · {{ $o->phone }}
@endif

@if($o->tracking_note)
**Note from the editors:** {{ $o->tracking_note }}
@endif

<x-mail::button :url="route('orders.index')">View your orders</x-mail::button>
</x-mail::message>
