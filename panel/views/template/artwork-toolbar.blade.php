{{-- Search and List/Gallery switch of an .artwork-browser, wired up by panel.js --}}
<div class="artwork-browser-toolbar">
    {{-- A div, not a label: panel.js swallows the space key inside labels --}}
    <div class="artwork-search">
        <i class="fa fa-magnifying-glass"></i>
        <input type="search" class="form-control" placeholder="Search artwork" aria-label="Search artwork" autocomplete="off">
    </div>
    <span class="artwork-count" aria-live="polite"></span>
    <div class="artwork-view-toggle" role="group" aria-label="View">
        <button type="button" data-view="list" class="css-hint" data-title="List" aria-label="List view"><i class="fa fa-list"></i></button>
        <button type="button" data-view="gallery" class="css-hint" data-title="Gallery" aria-label="Gallery view"><i class="fa fa-grip"></i></button>
    </div>
</div>
