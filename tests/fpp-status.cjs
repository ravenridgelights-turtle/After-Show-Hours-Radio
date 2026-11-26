const assert=require('node:assert/strict');
const {audioBusy}=require('../assets/fpp-status.js');
const cases=require('./fixtures/fpp-status.json');
for(const test of cases)assert.equal(audioBusy(test.status),test.busy,test.label);
console.log('PASS '+cases.length+' FPP media/sequence status scenarios (JavaScript).');
