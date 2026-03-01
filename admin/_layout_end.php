  </main>
</div><!-- end flex-1 admin-content -->

<script>
  lucide.createIcons();

  // Admin Toast Helper
  function adminToast(msg, type='info') {
    const icons = { success:'✅', error:'❌', info:'ℹ️', warning:'⚠️' };
    const colors = { success:'bg-green-600', error:'bg-red-600', info:'bg-blue-600', warning:'bg-amber-600' };
    const t = document.createElement('div');
    t.className = `${colors[type]||colors.info} text-white text-sm font-semibold px-4 py-3 rounded-xl shadow-lg flex items-center gap-2 min-w-52`;
    t.innerHTML = `<span>${icons[type]||'💬'}</span><span>${msg}</span>`;
    document.getElementById('toast-admin')?.appendChild(t);
    setTimeout(()=>{ t.style.opacity=0; t.style.transition='opacity .3s'; setTimeout(()=>t.remove(),400); }, 3500);
  }

  // DataTable defaults (auto-apply to all tables with .dt-table)
  $(document).ready(function(){
    $('.dt-table').DataTable({
      pageLength: 15,
      responsive: true,
      language: {
        search: '🔍',
        lengthMenu: '_MENU_ lignes',
        zeroRecords: 'Aucun résultat',
        info: 'Page _PAGE_ / _PAGES_',
        paginate: { previous: '‹', next: '›' }
      }
    });
  });
</script>
</body>
</html>
