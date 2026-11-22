(() => {
  const views = Array.from(document.querySelectorAll('.dashboard-view[data-view]'));
  const menuLinks = Array.from(document.querySelectorAll('.sidebar-nav a[href^="#"]'));

  function showView(name) {
    const selected = views.find((view) => view.dataset.view === name)
      || views.find((view) => view.dataset.view === 'dashboard');

    views.forEach((view) => {
      view.hidden = view !== selected;
    });

    menuLinks.forEach((link) => {
      const active = link.getAttribute('href') === `#${selected.dataset.view}`;
      link.classList.toggle('active', active);
      if (active) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
  }

  function showLocationView() {
    showView(window.location.hash.slice(1));
  }

  menuLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
      const name = link.getAttribute('href').slice(1);
      if (!views.some((view) => view.dataset.view === name)) return;
      if (window.location.hash !== `#${name}`) {
        window.history.pushState(null, '', `#${name}`);
      }
      showLocationView();
      window.scrollTo(0, 0);
    });
  });

  window.addEventListener('popstate', showLocationView);
  window.addEventListener('hashchange', showLocationView);
  showLocationView();
})();
