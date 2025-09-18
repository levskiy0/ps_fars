{literal}
<script>
(()=>{
  const rawSvc = window.farsResizeServiceUrl || {/literal}{$farsServiceUrl|json_encode nofilter}{literal};
  const svc = (typeof rawSvc === 'string' ? rawSvc : '').replace(/\/+$/, '');
  window.farsResizeServiceUrl = svc;

  const dim = v => {
    if (typeof v === 'number') {
      return v > 0 ? Math.round(v) : 0;
    }
    if (typeof v === 'string' && v.trim() !== '') {
      const n = parseInt(v, 10);
      return Number.isFinite(n) && n > 0 ? n : 0;
    }
    return 0;
  };

  const clean = src => {
    if (!src) {
      return '';
    }
    const withoutHost = src.replace(/^(?:https?:)?\/\/[^/]+/i, '') || src;
    const safe = withoutHost.includes(' ') ? withoutHost.replace(/ /g, '%20') : withoutHost;
    return '/' + safe.replace(/^\/+/, '');
  };

  window.fars_url = params => {
    if (!params || typeof params !== 'object') {
      return '';
    }
    const raw = params.src || params.url;
    if (!raw) {
      return '';
    }

    const path = clean(String(raw));
    if (!path || !svc) {
      return '';
    }

    const w = dim(params.width ?? params.w);
    const h = dim(params.height ?? params.h);
    const size = (w || h) ? `${w || ''}x${h || ''}` : '0x0';
    const base = `${svc}/resize/${size}${path}`;
    const format = (params.format ?? '').toString().trim().replace(/^\./, '');

    return format ? `${base}.${format}` : base;
  };
})();
</script>
{/literal}
