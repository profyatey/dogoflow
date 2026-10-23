<?php $pageTitle = $pageTitle ?? 'Admin'; $pageDescription = $pageDescription ?? 'DogoFlow administration'; ?>
<header class="sticky top-0 z-[1000] flex h-[76px] items-center gap-4 border-b border-slate-200 bg-white/[.97] px-8 shadow-[0_2px_14px_rgba(7,26,51,.045)] max-[820px]:px-[17px]">
    <button class="hidden h-[42px] w-[42px] cursor-pointer place-items-center rounded-[10px] border border-slate-200 bg-white text-[1.1rem] text-[#24506F] transition hover:border-[#9EDBFA] hover:bg-sky-50 hover:text-brand-hover max-[820px]:grid" type="button" id="adminMenuBtn" aria-label="Open administration menu" aria-expanded="false">☰</button>
    <div>
        <div class="text-base font-extrabold text-heading"><?= e($pageTitle) ?></div>
        <div class="mt-0.5 text-[.76rem] text-slate-500 max-[560px]:hidden"><?= e($pageDescription) ?></div>
    </div>
    <div class="flex-1"></div>
    <div class="flex items-center gap-[9px]">
        <a class="grid h-10 w-10 place-items-center rounded-[11px] border border-slate-200 bg-white text-[#24506F] no-underline transition hover:border-[#9EDBFA] hover:bg-sky-50 hover:text-brand-hover" href="<?=url('/')?>" target="_blank" title="View storefront">🌐</a>
        <a class="grid h-10 w-10 place-items-center rounded-[11px] border border-slate-200 bg-white text-[#24506F] no-underline transition hover:border-[#9EDBFA] hover:bg-sky-50 hover:text-brand-hover" href="<?=url('/admin/audit-logs.php')?>" title="Audit logs">🛡️</a>
    </div>
</header>
<script>
(function(){
 const sidebar=document.getElementById('adminSidebar'),btn=document.getElementById('adminMenuBtn'),back=document.getElementById('adminBackdrop');
 if(!sidebar||!btn)return;
 function open(){sidebar.classList.add('open');back.hidden=false;btn.setAttribute('aria-expanded','true');document.body.classList.add('overflow-hidden');}
 function close(){sidebar.classList.remove('open');back.hidden=true;btn.setAttribute('aria-expanded','false');document.body.classList.remove('overflow-hidden');}
 btn.addEventListener('click',()=>sidebar.classList.contains('open')?close():open());
 back.addEventListener('click',close); document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
 document.querySelectorAll('#adminSidebar a').forEach(a=>a.addEventListener('click',()=>{if(window.innerWidth<=820)close();}));
})();
</script>
