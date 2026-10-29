@props(['tone' => 'grey'])
<span {{ $attributes->class(['badge', 'rounded-pill', "badge-soft-{$tone}"]) }}>{{ $slot }}</span>
