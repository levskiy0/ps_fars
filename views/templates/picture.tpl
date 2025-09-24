<picture>
    {foreach from=$fallbacks item=bp}
        {assign var=_bw value=$bp.w|intval}
        {assign var=_bh value=$bp.h|intval}

        {assign var=_raw1x value="`$service`/resize/`$_bw`x`$_bh``$src`"}
        {if $hdpi}
            {assign var=_bw2 value=$_bw*2}
            {assign var=_bh2 value=$_bh*2}
            {assign var=_raw2x value="`$service`/resize/`$_bw2`x`$_bh2``$src`"}
        {else}
            {assign var=_raw2x value=''}
        {/if}

        {assign var=_media value=$bp.media|trim}
        {if $_media ne ''}
            {assign var=_media_attr value=" media=\"`$_media`\""}
        {else}
            {assign var=_media_attr value=''}
        {/if}

        {foreach from=$formats item=format}
            {capture assign=_srcset}{$_raw1x}{$format.extension}{if $hdpi && $_raw2x && $_raw2x ne $_raw1x}, {$_raw2x}{$format.extension} 2x{/if}{/capture}
            <source{$_media_attr nofilter} type="{$format.mime}"
                    srcset="{$_srcset|trim}">
        {/foreach}
    {/foreach}

    {assign var=_bw value=$w|intval}
    {assign var=_bh value=$h|intval}

    {assign var=raw1x value="`$service`/resize/`$_bw`x`$_bh``$src`"}
    {if $hdpi}
        {assign var=_bw2 value=$_bw*2}
        {assign var=_bh2 value=$_bh*2}
        {assign var=raw2x value="`$service`/resize/`$_bw2`x`$_bh2``$src`"}
    {else}
        {assign var=raw2x value=''}
    {/if}

    {foreach from=$formats item=format}
        {capture assign=_baseSrcset}{$raw1x}{$format.extension}{if $hdpi && $raw2x && $raw2x ne $raw1x}, {$raw2x}{$format.extension} 2x{/if}{/capture}
        <source type="{$format.mime}"
                srcset="{$_baseSrcset|trim}">
    {/foreach}

    {if $hdpi && $raw2x && $raw2x ne $raw1x}
        {assign var=_img_srcset value="{$raw1x} 1x, {$raw2x} 2x"}
    {else}
        {assign var=_img_srcset value="{$raw1x}"}
    {/if}

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
        srcset="{$_img_srcset|trim}"

        {foreach from=$data key=dn item=dt}data-{$dn}="{$dt}"{/foreach}

        {if $sizes ne ''}sizes="{$sizes}"{/if}
        {if $w|intval>0}width="{$w|intval}"{/if}
        {if $h|intval>0}height="{$h|intval}"{/if}
    />
</picture>
