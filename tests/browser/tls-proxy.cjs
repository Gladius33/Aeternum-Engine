'use strict';
// Browser CI only: actual TLS termination, no external network listener.
// The untrusted client's forwarding headers are always replaced, not forwarded.
const https=require('node:https');
const http=require('node:http');
const fs=require('node:fs');
const keyPath=process.env.AETERNUM_TEST_TLS_KEY;
const certPath=process.env.AETERNUM_TEST_TLS_CERT;
if (!keyPath || !certPath) throw new Error('CI TLS fixture requires ephemeral key and certificate');
const tls=https.createServer({key:fs.readFileSync(keyPath),cert:fs.readFileSync(certPath)},(request,response)=>{
    const headers={...request.headers};
    for(const key of ['x-forwarded-for','forwarded','x-real-ip','x-forwarded-host','x-forwarded-proto','x-forwarded-port']) delete headers[key];
    const peer=request.socket.remoteAddress;
    if (!peer) {response.writeHead(400).end();return;}
    headers['x-forwarded-for']=peer;
    headers['x-forwarded-proto']='https';
    headers.host='127.0.0.1:8765';
    const upstream=http.request({
        hostname:'127.0.0.1',port:8765,path:request.url,
        method:request.method,headers,timeout:15000
    },(upstreamResponse)=>{
        response.writeHead(upstreamResponse.statusCode||502,upstreamResponse.headers);
        upstreamResponse.pipe(response);
    });
    upstream.on('timeout',()=>upstream.destroy(new Error('Upstream timeout')));
    upstream.on('error',()=>{if(!response.headersSent)response.writeHead(502);response.end();});
    request.on('aborted',()=>upstream.destroy());
    request.pipe(upstream);
});
tls.listen(8777,'127.0.0.1');
