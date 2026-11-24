/* G22: bilingual apprenticeship. No commands, network requests or invented achievements. */
'use strict';
(()=>{
    const lesson=(id,kind,target,art,en,fr)=>Object.freeze({id,kind,target,art,copy:Object.freeze({en:Object.freeze(en),fr:Object.freeze(fr)})});
    const build=(id,target,en,fr)=>lesson(id,'building',target,target,en,fr);
    const action=(id,target,art,en,fr)=>lesson(id,'receipt',target,art,en,fr);
    const tracks=Object.freeze({
        medieval:Object.freeze([
            build('timber','lumberyard',['Establish the lumberyard','Open construction, select an owned plot and place a lumberyard. Inspect the foundation, check the costs and start work. The server worker confirms completion.','Wood is essential for further construction.'],['Établir la scierie','Ouvrez la construction, choisissez une parcelle possédée et placez une scierie. Inspectez la fondation, vérifiez les coûts et lancez les travaux. Le serveur confirme leur achèvement.','Le bois est essentiel aux prochaines constructions.']),
            build('farm','farm',['Build a farm','Place and complete a farm. It increases housing capacity and lets you welcome additional residents.','Check the reserve before committing resources.'],['Construire une ferme','Placez et achevez une ferme. Elle augmente la capacité d’accueil et permet de recevoir de nouveaux habitants.','Vérifiez les réserves avant d’engager des ressources.']),
            action('resident','settlement.feed','farm',['Welcome a resident','Open the domain actions and welcome a resident. You need food and available housing.','A successful action increases the population.'],['Accueillir un habitant','Ouvrez les actions du domaine et accueillez un habitant. Il faut des céréales et une place disponible.','Une action réussie augmente la population.']),
            action('work','settlement.work','lumberyard',['Organise a lumber shift','With a completed lumberyard and available workers, use Harvest timber. The server checks the food cost and cooldown.','An accepted shift produces wood.'],['Organiser une coupe de bois','Avec une scierie achevée et des travailleurs disponibles, utilisez Récolter du bois. Le serveur contrôle le coût alimentaire et le délai entre les coupes.','Une coupe acceptée produit du bois.']),
            build('storage','granary',['Expand housing and storage','Complete a granary after the required farm. Consult the construction prerequisites.','Housing helps you reach the population objective.'],['Développer les réserves et le logement','Achevez un grenier après la ferme requise. Consultez les prérequis de construction.','La capacité d’accueil aide à atteindre l’objectif démographique.']),
            build('keep','keep',['Raise a keep','Build a keep once its prerequisites are satisfied. Then consult the campaign for required levels and population.','The tutorial does not replace the official victory requirements.'],['Élever un donjon','Construisez un donjon lorsque ses prérequis sont satisfaits. Consultez ensuite la campagne pour les niveaux et la population exigés.','Le tutoriel ne remplace pas les conditions officielles de victoire.'])
        ]),
        industrial:Object.freeze([
            build('power','power_plant',['Build a power plant','Place a power plant on an owned plot, inspect the foundation and start the work. Wait for the confirmed completion.','Energy is needed for your industrial network.'],['Construire une centrale','Placez une centrale sur une parcelle possédée, inspectez la fondation et lancez les travaux. Attendez leur achèvement confirmé.','L’énergie est nécessaire au réseau industriel.']),
            action('generation','industry.generate','power_plant',['Generate energy','Use Generate energy in your domain. The server checks the raw material cost.','Check the new energy reserve after confirmation.'],['Produire de l’énergie','Utilisez Produire de l’énergie dans votre domaine. Le serveur vérifie le coût en matières premières.','Consultez la nouvelle réserve énergétique après confirmation.']),
            build('warehouse','warehouse',['Build a warehouse','Complete a warehouse to provide storage for manufactured goods.','Consult the logistics and reserves before producing.'],['Construire un entrepôt','Achevez un entrepôt afin de disposer d’une capacité de stockage pour les marchandises fabriquées.','Consultez la logistique et les réserves avant de produire.']),
            build('factory','steelworks',['Build a steelworks','Complete a steelworks with the necessary power infrastructure.','The server enforces all construction prerequisites.'],['Construire une aciérie','Achevez une aciérie avec les infrastructures énergétiques nécessaires.','Le serveur impose tous les prérequis de construction.']),
            action('manufacture','industry.refine','steelworks',['Manufacture goods','Use Manufacture goods once the factory and warehouse are ready. Check steel, energy and capacity.','Only the confirmed command counts towards this lesson.'],['Fabriquer des marchandises','Utilisez Fabriquer des marchandises lorsque l’aciérie et l’entrepôt sont prêts. Vérifiez l’acier, l’énergie et la capacité.','Seule la commande confirmée valide cette leçon.']),
            build('terminal','freight_terminal',['Open a freight terminal','Check the terminal prerequisites and upgrade the required infrastructure before building it.','The campaign shows the required levels.'],['Ouvrir un terminal de fret','Vérifiez les prérequis du terminal et améliorez les infrastructures requises avant sa construction.','La campagne indique les niveaux exigés.']),
            action('dispatch','industry.ship','freight_terminal',['Dispatch freight','Use Dispatch freight with a completed terminal and available goods and energy.','The campaign records actual deliveries.'],['Expédier du fret','Utilisez Expédier du fret avec un terminal achevé, des marchandises et de l’énergie disponibles.','La campagne comptabilise les livraisons effectives.'])
        ]),
        guild:Object.freeze([
            build('hall','market_hall',['Establish your market hall','Choose an owned plot, place a market hall, inspect the foundation and start construction.','Wait for the server worker to confirm completion.'],['Établir votre halle marchande','Choisissez une parcelle possédée, placez une halle, inspectez la fondation et lancez les travaux.','Attendez la confirmation d’achèvement par le serveur.']),
            action('offer','market.create','market_hall',['Publish a barter offer','Open Trade. Choose distinct offered and requested resources, enter positive whole quantities and publish.','The server reserves the offered goods; publication is not a completed exchange.'],['Publier une offre de troc','Ouvrez Commerce. Choisissez deux ressources distinctes, saisissez des quantités entières positives et publiez.','Le serveur réserve les biens offerts ; une publication n’est pas un échange achevé.']),
            build('hub','caravan_hub',['Establish a caravan hub','Build a caravan hub after meeting its prerequisites. Review the costs and reserve first.','Expand your merchant estate without inventing a victory condition.'],['Établir un relais caravanier','Construisez un relais caravanier après avoir satisfait ses prérequis. Consultez d’abord les coûts et les réserves.','Développez votre domaine marchand sans condition de victoire fictive.'])
        ])
    });
    const messages=Object.freeze({
        en:Object.freeze({heading:'Apprenticeship',intro:'Optional interactive lessons using your real domain.',scope:'Buildings use server readings; actions use confirmations from this session only. No extra rewards.',complete:'Confirmed',pending:'Under construction',unknown:'Not verified',incomplete:'To do',summary:'lessons confirmed',go:'Open this step',refresh:'Refresh holdings',previous:'Previous lesson',next:'Next lesson',follow:'Follow next objective',finished:'All tutorial lessons confirmed. Continue with the campaign or trade.',buildHint:'Choose or inspect the building, then use its existing construction controls.',waitHint:'Wait for the worker or refresh the building.',actionHint:'Use the indicated existing action; the tutorial never sends it for you.',unsupported:'This building is not available in the loaded catalogue.',review:'Review lesson'}),
        fr:Object.freeze({heading:'Apprentissage',intro:'Leçons interactives facultatives dans votre domaine réel.',scope:'Bâtiments : relevés serveur ; actions : confirmations de cette session uniquement. Aucune récompense supplémentaire.',complete:'Confirmée',pending:'En construction',unknown:'Non vérifiée',incomplete:'À faire',summary:'leçons confirmées',go:'Ouvrir cette étape',refresh:'Actualiser les propriétés',previous:'Leçon précédente',next:'Leçon suivante',follow:'Suivre le prochain objectif',finished:'Toutes les leçons du tutoriel sont confirmées. Poursuivez la campagne ou le commerce.',buildHint:'Choisissez ou inspectez le bâtiment, puis utilisez ses commandes de construction.',waitHint:'Attendez la confirmation du serveur ou actualisez le bâtiment.',actionHint:'Utilisez l’action indiquée ; le tutoriel ne l’envoie jamais à votre place.',unsupported:'Ce bâtiment ne figure pas dans le catalogue chargé.',review:'Revoir la leçon'})
    });
    function project(theme,{known=false,holdings=[],confirmed=[]}={}){
        const track=Object.hasOwn(tracks,theme)?tracks[theme]:[];
        const receipts=new Set(confirmed);
        const rows=track.map(l=>{
            let state='unknown';
            if(l.kind==='receipt')state=receipts.has(l.target)?'complete':'incomplete';
            else{
                const matches=holdings.filter(b=>b.type===l.target);
                if(matches.some(b=>Number.isSafeInteger(b.level)&&b.level>0))state='complete';
                else if(matches.some(b=>b.pending===true))state='pending';
                else if(known&&matches.every(b=>Number.isSafeInteger(b.level)&&b.level===0))state='incomplete';
            }
            return Object.freeze({...l,state});
        });
        const next=rows.findIndex(l=>l.state!=='complete'),done=rows.filter(l=>l.state==='complete').length;
        return Object.freeze({rows:Object.freeze(rows),next,done,total:rows.length,finished:rows.length>0&&next===-1});
    }
    window.AeternumTutorial=Object.freeze({tracks,messages,project,version:1});
})();
