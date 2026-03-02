document.addEventListener('DOMContentLoaded', () => {
  const btnSidebar = document.getElementById('btnSidebar');
  const sidebar = document.getElementById('sidebar');
  if (btnSidebar && sidebar) {
    btnSidebar.addEventListener('click', () => {
      sidebar.classList.toggle('hidden');
      sidebar.classList.toggle('absolute');
      sidebar.classList.toggle('z-50');
      sidebar.classList.toggle('h-screen');
    });
  }
  // Tema (placeholder: alterna classe, se quiser claro/escuro no futuro)
  const themeBtn = document.getElementById('themeToggle');
  if (themeBtn) themeBtn.addEventListener('click',() => {
    document.body.classList.toggle('theme-alt');
  });

  // Pequenos toques: animação de clique em botões
  document.querySelectorAll('.btn').forEach(btn=>{
    btn.addEventListener('mousedown',()=>btn.style.transform='translateY(1px)');
    btn.addEventListener('mouseup',()=>btn.style.transform='translateY(0)');
  });
});
