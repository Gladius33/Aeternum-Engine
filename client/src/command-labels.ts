import type {Language} from './messages';
const commands: Record<string,{en:string;fr:string}> = {
  'economy.settle': {en:'Production settled',fr:'Production calculée'},
  'economy.upgrade': {en:'Production upgrade submitted',fr:'Amélioration de production enregistrée'},
  'economy.transfer': {en:'Resource transfer confirmed',fr:'Transfert de ressources confirmé'},
  'market.create': {en:'Barter offer published',fr:'Offre de troc publiée'},
  'market.accept': {en:'Barter exchange confirmed',fr:'Échange de troc confirmé'},
  'market.cancel': {en:'Barter offer cancelled',fr:'Offre de troc annulée'},
  'market.claim': {en:'Escrow refund claimed',fr:'Remboursement des ressources bloquées demandé'},
  'building.place': {en:'Foundation placed',fr:'Fondation créée'},
  'building.start': {en:'Construction started',fr:'Construction lancée'},
  'building.cancel': {en:'Construction cancelled',fr:'Construction annulée'},
  'building.claim': {en:'Construction refund claimed',fr:'Remboursement de construction demandé'},
  'settlement.feed': {en:'Resident welcomed',fr:'Habitant accueilli'},
  'settlement.work': {en:'Lumber shift organized',fr:'Travail du bois organisé'},
  'industry.generate': {en:'Electricity generated',fr:'Électricité produite'},
  'industry.refine': {en:'Goods manufactured',fr:'Marchandises fabriquées'},
  'industry.ship': {en:'Freight dispatched',fr:'Marchandises expédiées'},
  'pack.campaign.research': {en:'Research scheduled',fr:'Recherche planifiée'},
  'pack.campaign.expedition': {en:'Expedition scheduled',fr:'Expédition planifiée'},
  'pack.guild.challenge': {en:'Challenge confirmed',fr:'Défi confirmé'},
  'pack.diplomacy.offer': {en:'Truce proposed',fr:'Trêve proposée'},
  'pack.diplomacy.accept': {en:'Truce accepted',fr:'Trêve acceptée'}
};
export function actionLabel(language: Language, action: string): string {
  return commands[action]?.[language] ?? action.replaceAll('.', ' ');
}
