;(() => {
  const settings = typeof GSEASYSettings !== "undefined" ? GSEASYSettings : {};
  const tooltipEnabled =
    settings.tooltipEnable === "1" ||
    settings.tooltipEnable === 1 ||
    settings.tooltipEnable === true;

  if (tooltipEnabled) {
    const links = document.querySelectorAll('a.gseasy-term[data-tooltip-preview]');

    links.forEach(link => {
      let tooltip;

      const show = () => {
        if (tooltip) return;
        const text = link.dataset.tooltipPreview || 'No definition available';

        tooltip = document.createElement('div');
        tooltip.className = 'gseasy-tooltip ' + (settings.tooltipStyle || '');
        tooltip.setAttribute('role', 'tooltip');
        tooltip.textContent = text;

        document.body.appendChild(tooltip);

        const rect = link.getBoundingClientRect();
        tooltip.style.position = 'absolute';
        tooltip.style.top = rect.bottom + window.scrollY + 6 + 'px';
        tooltip.style.left = rect.left + window.scrollX + 'px';
        tooltip.style.display = 'block';
      };

      const hide = () => {
        if (tooltip) { tooltip.remove(); tooltip = null; }
      };

      link.addEventListener('mouseenter', show);
      link.addEventListener('mouseleave', hide);
      window.addEventListener('scroll', hide, { passive: true });
      window.addEventListener('resize', hide);
    });
  }

  const scrollButtons = document.querySelectorAll('.gseasy-scroll-up');
  if (!scrollButtons.length) return;

  scrollButtons.forEach(button => {
    button.addEventListener('click', () => {
      const target = document.querySelector('.gseasy-search-form, .gseasy-alphabet-filter');
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  });
})();
