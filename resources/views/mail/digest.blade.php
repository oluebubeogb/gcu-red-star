<x-mail::message>
# Hello {{ $user->name }},

Here is what's new in **The Red Star** this month.

@if(count($d['editions']))
## New editions
@foreach($d['editions'] as $e)
- [{{ $e->title.($e->year ? " ({$e->year})" : '') }}]({{ route('editions.show', $e->slug) }})
@endforeach
@endif

@if(count($d['announcements']))
## From the editors
@foreach($d['announcements'] as $a)
**{{ $a->title }}** — {{ \Illuminate\Support\Str::limit($a->body, 220) }}

@endforeach
@endif

@if($d['feature'])
## Old Boy of the month: {{ $d['feature']['name'] }}
{{ \Illuminate\Support\Str::limit($d['feature']['blurb'], 400) }}
@if($d['feature']['public'])
[Read their profile]({{ route('members.show', $d['feature']['username']) }})
@endif
@endif

@if(!empty($d['ad']))
@php
    $adLine = '**'.$d['ad']['title'].'**'.($d['ad']['body'] ? ' — '.\Illuminate\Support\Str::limit($d['ad']['body'], 300) : '');
@endphp
<x-mail::panel>
SPONSORED · {{ $d['ad']['advertiser'] }}

{!! e($adLine) !!}

@if($d['ad']['url'])
[Learn more]({{ $d['ad']['url'] }})
@endif
</x-mail::panel>
@endif

## Homecoming {{ $d['homecoming']['year'] }}
@php
    $ev = $d['homecoming']['event'];
    $when = $ev && $ev['starts_on'] ? ' on '.\Illuminate\Support\Carbon::parse($ev['starts_on'])->format('j F Y').($ev['venue'] ? ' at '.$ev['venue'] : '') : '';
@endphp
The **Class of {{ $d['homecoming']['set'] }}** celebrates its golden jubilee{{ $when }}.

<x-mail::button :url="route('homecoming')">Homecoming details</x-mail::button>

<x-mail::subcopy>
You receive this because you opted in to the monthly digest. [Unsubscribe]({{ $unsubscribeUrl }}) · or change it in your profile.
</x-mail::subcopy>
</x-mail::message>
