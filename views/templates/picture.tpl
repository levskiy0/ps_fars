<picture>
    {foreach from=$fallbacks item=bp}
        {assign var=_bw value=$bp.w|intval}
        {assign var=_bh value=$bp.h|intval}
        {assign var=_bw2 value=$_bw*2}
        {assign var=_bh2 value=$_bh*2}

        {assign var=_raw1x value="`$service`/resize/`$_bw`x`$_bh``$src`"}
        {assign var=_raw2x value="`$service`/resize/`$_bw2`x`$_bh2``$src`"}
        {assign var=_media value=$bp.media|trim}
        {if $_media ne ''}
            {assign var=_media_attr value=" media=\"`$_media`\""}
        {else}
            {assign var=_media_attr value=''}
        {/if}

        {foreach from=$formats item=format}
            <source{$_media_attr nofilter} type="{$format.mime}"
                    srcset="{$_raw1x}{$format.extension}, {$_raw2x}{$format.extension} 2x">
        {/foreach}
    {/foreach}

    {assign var=_bw value=$w|intval}
    {assign var=_bh value=$h|intval}
    {assign var=_bw2 value=$_bw*2}
    {assign var=_bh2 value=$_bh*2}

    {assign var=raw1x value="`$service`/resize/`$_bw`x`$_bh``$src`"}
    {assign var=raw2x value="`$service`/resize/`$_bw2`x`$_bh2``$src`"}

    {foreach from=$formats item=format}
        <source type="{$format.mime}"
                srcset="{$raw1x}{$format.extension}, {$raw2x}{$format.extension} 2x">
    {/foreach}

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
