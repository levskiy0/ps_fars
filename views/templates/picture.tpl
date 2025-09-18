<picture>
    {foreach from=$fallbacks item=bp}
        {assign var=_bw value=$bp.w|intval}
        {assign var=_bh value=$bp.h|intval}
        {assign var=_bw2 value=$_bw*2}
        {assign var=_bh2 value=$_bh*2}

        {assign var=_raw1x value="`$service`/resize/`$_bw`x`$_bh``$src`"}
        {assign var=_raw2x value="`$service`/resize/`$_bw2`x`$_bh2``$src`"}

        <source media="{$bp.media}" type="image/avif"
                srcset="{$_raw1x}.avif, {$_raw2x}.avif 2x">
        <source media="{$bp.media}" type="image/webp"
                srcset="{$_raw1x}.webp, {$_raw2x}.webp 2x">
        <source media="{$bp.media}" type="image/jpeg"
                srcset="{$_raw1x}, {$_raw2x} 2x">
    {/foreach}

    {assign var=_bw value=$w|intval}
    {assign var=_bh value=$h|intval}
    {assign var=_bw2 value=$_bw*2}
    {assign var=_bh2 value=$_bh*2}

    {assign var=raw1x value="`$service`/resize/`$_bw`x`$_bh``$src`"}
    {assign var=raw2x value="`$service`/resize/`$_bw2`x`$_bh2``$src`"}

    <source type="image/avif"
            srcset="{$raw1x}.avif, {$raw2x}.avif 2x">
    <source type="image/webp"
            srcset="{$raw1x}.webp, {$raw2x}.webp 2x">
    <source type="image/jpeg"
            srcset="{$raw1x}, {$raw2x} 2x">

    <img
        class="{$class}"
        src="{$raw1x}"
        alt="{$alt}"
        {if $loading ne ""}
        loading="{$loading}"
        {/if}
        {if $decoding ne ""}
        decoding="{$decoding}"
        {/if}
        {if $fetchpriority ne ""}
        fetchpriority="{$fetchpriority}"
        {/if}
        srcset="{$raw1x} 1x, {$raw2x} 2x"

        {foreach from=$data key=dn item=dt}data-{$dn}="{$dt}"{/foreach}

        {if $sizes ne ''}sizes="{$sizes}"{/if}
        {if $w|intval>0}width="{$w|intval}"{/if}
        {if $h|intval>0}height="{$h|intval}"{/if}
    />
</picture>