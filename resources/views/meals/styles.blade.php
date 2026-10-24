<style>
    .mp-page { width:100%; color:#183d32; }
    .mp-page h1 { margin:0 0 10px; }
    .mp-muted { color:#6b7d71; line-height:1.7; }
    .mp-toolbar {
        display:flex; flex-wrap:wrap; align-items:center;
        gap:14px; margin:22px 0;
    }
    .mp-grid {
        display:grid; grid-template-columns:repeat(2,minmax(0,1fr));
        gap:20px;
    }
    .mp-card {
        min-width:0; padding:22px; border:1px solid #dfe7e1;
        border-radius:15px; background:#fff; margin-bottom:18px;
    }
    .mp-card h2 { margin:0 0 12px; font-size:21px; }
    .mp-card h3 { margin:0 0 12px; }
    .mp-button {
        display:inline-block; padding:11px 17px; border:0;
        border-radius:8px; background:#1c4936; color:#fff;
        text-decoration:none; font:inherit; cursor:pointer;
    }
    .mp-remove { background:#875247; }
    .mp-page input, .mp-page select {
        box-sizing:border-box; padding:11px; border:1px solid #cedbd1;
        border-radius:8px; background:#fff; color:#183d32; font:inherit;
    }
    .mp-add {
        display:flex; flex-wrap:wrap; gap:10px;
        align-items:end; margin-top:20px;
    }
    .mp-add label { display:block; font-size:13px; margin-bottom:6px; }
    .mp-stats {
        display:grid; grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px; margin:20px 0;
    }
    .mp-stat {
        background:#edf3ed; border-radius:10px; padding:15px;
    }
    .mp-stat strong { display:block; font-size:22px; margin-top:6px; }
    .mp-nutrition { line-height:1.8; font-weight:600; }
    .mp-card details { margin-top:16px; }
    .mp-card summary { cursor:pointer; font-weight:700; }
    .mp-ingredients { list-style:none; padding:0; }
    .mp-ingredients li {
        display:flex; justify-content:space-between; gap:14px;
        padding:10px 0; border-bottom:1px solid #edf0ec;
    }
    .mp-ingredients small { display:block; color:#718074; }
    .mp-method { white-space:pre-line; line-height:1.8; }
    .mp-errors {
        background:#fff0ed; color:#843f32; padding:16px;
        border-radius:10px; margin:18px 0;
    }
    @media(max-width:900px) {
        .mp-grid { grid-template-columns:1fr; }
        .mp-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
</style>