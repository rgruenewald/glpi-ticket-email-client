// NODE_PATH=/tmp/ticketmailer-ui-bench/node_modules node scripts/compare-ui-screenshots.cjs BEFORE AFTER
const fs = require('node:fs');
const assert = require('node:assert/strict');
const { PNG } = require('pngjs');
for (const width of [1280, 390]) {
 const a=PNG.sync.read(fs.readFileSync(process.argv[2].replace(/\.json$/, '-'+width+'.png')));
 const b=PNG.sync.read(fs.readFileSync(process.argv[3].replace(/\.json$/, '-'+width+'.png')));
 assert.equal(a.width,b.width);assert.equal(a.height,b.height);
 let changed=0;for(let i=0;i<a.data.length;i+=4){if(!a.data.subarray(i,i+4).equals(b.data.subarray(i,i+4)))changed++;}
 console.log(width+': '+changed+' changed pixels');
 assert.equal(changed,0,'fixture appearance must remain unchanged');
}
