;(() => {
  const enabled =
    typeof GSEASYSettings !== "undefined" &&
    (GSEASYSettings.tooltipEnable === "1" ||
     GSEASYSettings.tooltipEnable === 1 ||
     GSEASYSettings.tooltipEnable === true);

  if (!enabled) return;

  const links = document.querySelectorAll('a.gseasy-term[data-tooltip-preview]');

  links.forEach(link => {
    let tooltip;

    const show = () => {
      if (tooltip) return;
      const text = link.dataset.tooltipPreview || 'No definition available';

      tooltip = document.createElement('div');
      tooltip.className = 'gseasy-tooltip ' + (GSEASYSettings.tooltipStyle || '');
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
})();