/* Original Aeternum game UI presentation profiles. No game rules or network writes. */
'use strict';
(()=>{
    const pair=(en,fr)=>Object.freeze({en,fr});
    const shared={
        atlas:pair('World atlas','Atlas du monde'),domain:pair('Domain','Domaine'),
        holdings:pair('Buildings','Bâtiments'),construction:pair('Build','Construire'),
        reserves:pair('Resources','Ressources'),objectives:pair('Campaign','Campagne'),
        market:pair('Trade','Commerce'),relations:pair('Relations','Relations'),activity:pair('Activity','Activité'),settings:pair('Detailed controls','Commandes détaillées')
    };
    const make=(id,title,subtitle,nav)=>Object.freeze({id,title,subtitle,labels:shared,nav:Object.freeze(nav)});
    const profiles={
        neutral:make('neutral',pair('Aeternum','Aeternum'),pair('World management','Gestion du monde'),['atlas','domain','holdings','construction','reserves','market','relations','activity']),
        medieval:make('medieval',pair('The living realm','Le royaume vivant'),pair('Grow your settlement','Développez votre colonie'),['domain','atlas','construction','holdings','reserves','objectives','market','relations','activity']),
        industrial:make('industrial',pair('Industrial frontier','La frontière industrielle'),pair('Build a productive network','Développez votre réseau productif'),['domain','atlas','construction','holdings','reserves','objectives','market','relations','activity']),
        guild:make('guild',pair('Guild Caravans','Guild Caravans'),pair('Develop your merchant house','Développez votre maison marchande'),['domain','atlas','construction','holdings','reserves','objectives','market','relations','activity'])
    };
    window.AeternumUIProfiles=Object.freeze(profiles);
})();
