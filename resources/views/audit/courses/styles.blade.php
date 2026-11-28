@once
    <style>
        .audit-band { font-weight: 600; }
        .audit-band-delegacao-ii { background: #146c43; color: #fff; }
        .audit-band-delegacao-i { background: #2f9e6b; color: #fff; }
        .audit-band-gestao-ii { background: #1264a3; color: #fff; }
        .audit-band-gestao-i { background: #5b8fc7; color: #fff; }
        .audit-band-supervisao-ii { background: #e4a11b; color: #3b2a00; }
        .audit-band-supervisao-i { background: #b02a37; color: #fff; }
        .audit-ind { border: 1px solid #e3e9ef; border-radius: .7rem; background: #fff; margin-bottom: .6rem; scroll-margin-top: 90px; }
        .audit-ind > summary { list-style: none; cursor: pointer; padding: .75rem 1rem; display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .audit-ind > summary::-webkit-details-marker { display: none; }
        .audit-ind > summary::before { content: "›"; font-weight: 700; color: #6c7a8b; transition: transform .15s; width: .8rem; }
        .audit-ind[open] > summary::before { transform: rotate(90deg); }
        .audit-ind[open] > summary { border-bottom: 1px solid #eef2f6; }
        .audit-code { font-weight: 800; color: #0c3d67; min-width: 3.2rem; }
        .audit-opt { display: block; border: 1px solid #dfe6ed; border-radius: .5rem; padding: .45rem .6rem; cursor: pointer; font-size: .85rem; height: 100%; }
        .audit-opt:hover { background: #f4f8fc; }
        .btn-check:checked + .audit-opt { border-color: #1264a3; background: #e8f1fb; box-shadow: inset 0 0 0 1px #1264a3; }
        .btn-check:focus-visible + .audit-opt { outline: 3px solid #0c3d67; outline-offset: 1px; }
        .audit-opt strong { display: block; }
        .audit-group { margin: 1.25rem 0 .5rem; display: flex; gap: .5rem; align-items: baseline; flex-wrap: wrap; }
        .audit-docs td, .audit-docs th { font-size: .82rem; vertical-align: middle; }
    </style>
@endonce
