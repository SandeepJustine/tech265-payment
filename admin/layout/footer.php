  </div><!-- /content -->
</div><!-- /main -->
<script>
(function(){
  var btn = document.getElementById('hamburger');
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebar-overlay');
  if(!btn) return;
  function openSidebar(){
    sidebar.classList.add('open');
    overlay.classList.add('active');
    document.body.style.overflow='hidden';
  }
  function closeSidebar(){
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow='';
  }
  btn.addEventListener('click', function(){ sidebar.classList.contains('open') ? closeSidebar() : openSidebar(); });
  overlay.addEventListener('click', closeSidebar);
  sidebar.querySelectorAll('a').forEach(function(a){ a.addEventListener('click', function(){ if(window.innerWidth<=768) closeSidebar(); }); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeSidebar(); });
})();
</script>
</body>
</html>
