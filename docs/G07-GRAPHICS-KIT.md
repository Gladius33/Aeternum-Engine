# Aeternum Art v1 — reusable vector graphics and camera

The existing actual ten building models and material helpers have been extracted from the game-specific adapter into public/game-art.js. No external artwork or code is used. This library has no network, persistence, gameplay rules, or authentication responsibilities.

## Integration
Load game-art.js before your adapter. Create an instance with skin() and palette() providers returning the current visual skin and six colors. Use model(parent,type,{level,pending}) to render real authorized holdings. Unverified levels and pending construction use the original generic stage rendering. Existing reference packs use the same extracted art, not copied or duplicate implementations.

    const art = AeternumArt.create({skin:()=>theme,palette:()=>colors});
    art.register('custom-building',(layer,detail,draw)=>{
        draw.rect(layer,-20,-30,40,30,colors[2]);
        draw.poly(layer,'-24,-30 0,-48 24,-30',colors[3]);
    });
    art.model(svgGroup,'custom-building',serverBuilding);

Custom registered sprites render only for a positive verified level and no pending event. Renderer input is trusted developer code; never evaluate scripts from remote pack data. Each art instance allocates unique gradient IDs. Keep accessibility labels and permission checks in your adapter.

## Camera
    const view = AeternumArt.camera(svg,{min:1,max:3});
    view.size(900,510);
    view.zoom(1.25);
    view.reset();

The camera clamps offsets, supports pointer dragging on empty space and keyboard zoom/pan, and preserves selection clicks. Call destroy() when unmounting. Coordinates are illustrative presentation space, not real topology. Desktop and mobile extent changes reset the viewport. Tests exercise the real district and camera in all three campaigns.

## Activity and construction
The district shows the last server-acknowledged command from the current context. It does not invent movements, shipments, or production. An empty approved site is selectable and opens the existing construction catalogue. Reduced-motion users receive static feedback. Visual CI and actual screenshots remain mandatory before declaring this increment validated.
