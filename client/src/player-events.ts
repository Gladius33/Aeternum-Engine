// Presentation event validation only. Authorization remains on the server.
export type PlayerContext = {world:string;account:string};
export type PlayerReceipt = PlayerContext & {type:string;id:string};
const record=(value:unknown):Record<string,unknown>|null=>
  value!==null&&typeof value==='object'&&!Array.isArray(value)?value as Record<string,unknown>:null;
const id=(v:unknown,limit:number):v is string=>typeof v==='string'&&v.length<=limit&&/^[A-Za-z0-9][A-Za-z0-9_.:-]*$/.test(v);
export function playerContext(value:unknown):PlayerContext|null {
  const data=record(value);
  if(!data||!id(data.world,128)||!id(data.account,64))return null;
  return {world:data.world,account:data.account};
}
export function playerReceipt(value:unknown,scope:PlayerContext|null):PlayerReceipt|null {
  const data=record(value),context=playerContext(value);
  if(!data||!scope||!context||context.world!==scope.world||context.account!==scope.account
    ||typeof data.type!=='string'||! /^[a-z][a-z0-9_.-]{0,79}$/.test(data.type)||!id(data.id,128))return null;
  return {...context,type:data.type,id:data.id};
}
export function playerFlag(value:unknown,key:'busy'|'pending'):boolean {
  return record(value)?.[key]===true;
}
