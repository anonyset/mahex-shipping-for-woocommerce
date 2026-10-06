(()=>{'use strict';
const form=document.getElementById('hmx-board-form');if(!form||typeof hmMahexBoard==='undefined')return;
const message=document.getElementById('hmx-board-message');let dragged=null;
function announce(text,error=false){message.textContent=text;message.classList.toggle('hmx-board-error',error);}
function counts(){document.querySelectorAll('.hmx-board-column').forEach(column=>{column.querySelector('.hmx-board-count').textContent=column.querySelectorAll('.hmx-board-card').length;});}
async function save(card,stage,undo=false){
 if(card.dataset.busy==='1')return;card.dataset.busy='1';card.setAttribute('aria-busy','true');card.classList.add('hmx-board-saving');
 const data=new URLSearchParams({action:'hm_mahex_v32_board',nonce:hmMahexBoard.nonce,order_id:card.dataset.id,revision:card.dataset.revision,stage,operator:card.querySelector('.hmx-board-operator').value,undo:undo?'1':'0'});
 try{const response=await fetch(hmMahexBoard.url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data});const result=await response.json();if(!result.success)throw new Error(result.data?.message||'ذخیره انجام نشد.');
 const state=result.data;card.dataset.revision=state.revision;card.querySelector('.hmx-board-revision').value=state.revision;card.querySelector('.hmx-board-stage').value=state.stage;card.querySelector('.hmx-board-operator').value=state.operator;
 document.querySelector(`.hmx-board-column[data-stage="${state.stage}"] .hmx-board-cards`).append(card);card.querySelector('.hmx-board-undo').disabled=!state.history.length;counts();announce('سفارش #'+card.dataset.id+' ذخیره شد. برای مشاهده تاریخچه جدید صفحه را تازه کنید.');
 }catch(error){announce(error.message,true);card.querySelector('.hmx-board-stage').value=card.closest('.hmx-board-column').dataset.stage;}
 finally{card.dataset.busy='0';card.removeAttribute('aria-busy');card.classList.remove('hmx-board-saving');}
}
form.addEventListener('click',event=>{const button=event.target.closest('.hmx-board-save,.hmx-board-undo');if(!button)return;event.preventDefault();const card=button.closest('.hmx-board-card');save(card,card.querySelector('.hmx-board-stage').value,button.classList.contains('hmx-board-undo'));});
form.addEventListener('dragstart',event=>{const card=event.target.closest('.hmx-board-card');if(!card||card.dataset.busy==='1'||event.target.closest('select,input,button,a')){event.preventDefault();return;}dragged=card;event.dataTransfer.setData('text/plain',card.dataset.id);event.dataTransfer.effectAllowed='move';});
form.addEventListener('dragend',()=>{dragged=null;document.querySelectorAll('.hmx-board-drop').forEach(el=>el.classList.remove('hmx-board-drop'));});
form.querySelectorAll('.hmx-board-column').forEach(column=>{column.addEventListener('dragover',event=>{if(!dragged)return;event.preventDefault();event.dataTransfer.dropEffect='move';column.classList.add('hmx-board-drop');});column.addEventListener('dragleave',event=>{if(!column.contains(event.relatedTarget))column.classList.remove('hmx-board-drop');});column.addEventListener('drop',event=>{event.preventDefault();column.classList.remove('hmx-board-drop');if(dragged)save(dragged,column.dataset.stage);});});
document.getElementById('hmx-board-print').addEventListener('click',()=>{const selected=[...form.querySelectorAll('input[name="selected[]"]:checked')];if(!selected.length){announce('ابتدا سفارش‌ها را انتخاب کنید.',true);return;}form.querySelectorAll('.hmx-board-card').forEach(card=>card.classList.toggle('hmx-board-print-hide',!card.querySelector('input[name="selected[]"]').checked));window.print();form.querySelectorAll('.hmx-board-print-hide').forEach(card=>card.classList.remove('hmx-board-print-hide'));});
})();
