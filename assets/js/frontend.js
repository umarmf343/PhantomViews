(function () {
  const tours = document.querySelectorAll('.phantomviews-tour');
  if (!tours.length) {
    return;
  }

  const parseTourData = (text) => {
    if (!text) {
      return { scenes: [] };
    }

    try {
      const parsed = JSON.parse(text);
      if (Array.isArray(parsed)) {
        return { scenes: parsed };
      }
      return parsed || { scenes: [] };
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error('PhantomViews: Failed to parse tour data', error);
      return { scenes: [] };
    }
  };

  const applyTheme = (container, theme = {}) => {
    const defaults = {
      primary_color: '#0f172a',
      secondary_color: '#1e293b',
      accent_color: '#38bdf8',
      font_family: 'inherit',
    };

    const resolved = { ...defaults, ...theme };

    container.style.setProperty('--phantomviews-primary', resolved.primary_color);
    container.style.setProperty('--phantomviews-secondary', resolved.secondary_color);
    container.style.setProperty('--phantomviews-accent', resolved.accent_color);
    container.style.setProperty('--phantomviews-font', resolved.font_family);
  };

  const renderBranding = (container, branding = {}) => {
    const mode = branding.mode || 'default';
    let displayName = branding.brand_name;
    const logo = branding.brand_logo;
    const url = branding.brand_url;

    if (!displayName && mode !== 'white_label') {
      displayName = 'PhantomViews';
    }

    if (!displayName && !logo) {
      return;
    }

    const badge = document.createElement('div');
    badge.className = 'phantomviews-branding-badge';

    const content = url ? document.createElement('a') : document.createElement('span');
    content.className = 'phantomviews-branding-content';
    if (url) {
      content.href = url;
      content.target = '_blank';
      content.rel = 'noopener noreferrer';
    }

    if (logo) {
      const image = document.createElement('img');
      image.src = logo;
      image.alt = displayName || '';
      image.loading = 'lazy';
      content.appendChild(image);
    }

    if (displayName) {
      const text = document.createElement('span');
      text.textContent = displayName;
      content.appendChild(text);
    }

    badge.appendChild(content);
    container.appendChild(badge);
  };

  const renderAudioControls = (container, audioTracks = []) => {
    const validTracks = audioTracks.filter((track) => track && track.url);
    if (!validTracks.length) {
      return;
    }

    const audioWrapper = document.createElement('div');
    audioWrapper.className = 'phantomviews-audio-controller';

    const label = document.createElement('span');
    label.className = 'phantomviews-audio-label';
    label.textContent = 'Audio';
    audioWrapper.appendChild(label);

    const select = document.createElement('select');
    select.className = 'phantomviews-audio-select';

    validTracks.forEach((track, index) => {
      const option = document.createElement('option');
      option.value = track.url;
      option.textContent = track.label || `Track ${index + 1}`;
      select.appendChild(option);
    });

    const audio = document.createElement('audio');
    audio.controls = true;
    audio.preload = 'none';
    audio.className = 'phantomviews-audio-player';
    audio.src = validTracks[0].url;

    select.value = validTracks[0].url;

    select.addEventListener('change', (event) => {
      audio.src = event.target.value;
      audio.play().catch(() => {
        /* playback may be blocked until user interaction */
      });
    });

    audioWrapper.appendChild(select);
    audioWrapper.appendChild(audio);
    container.appendChild(audioWrapper);
  };

  const renderFloorPlans = (container, floorPlans = []) => {
    const validPlans = floorPlans.filter((plan) => plan && plan.image_url);
    if (!validPlans.length) {
      return;
    }

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'phantomviews-floorplan-toggle';
    toggle.textContent = 'Floor plans';
    toggle.setAttribute('aria-expanded', 'false');

    const panel = document.createElement('div');
    panel.className = 'phantomviews-floorplan-panel';
    panel.setAttribute('aria-hidden', 'true');

    validPlans.forEach((plan) => {
      const item = document.createElement('figure');
      item.className = 'phantomviews-floorplan-item';

      const img = document.createElement('img');
      img.src = plan.image_url;
      img.alt = plan.title || 'Floor plan';
      img.loading = 'lazy';

      const caption = document.createElement('figcaption');
      caption.textContent = plan.title || '';

      item.appendChild(img);
      if (caption.textContent) {
        item.appendChild(caption);
      }
      panel.appendChild(item);
    });

    toggle.addEventListener('click', () => {
      const isOpen = panel.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    });

    container.appendChild(toggle);
    container.appendChild(panel);
  };

  tours.forEach((container) => {
    const tourId = container.dataset.tourId;
    const dataScript = document.querySelector(`script.phantomviews-data[data-tour-id="${tourId}"]`);
    if (!dataScript) {
      return;
    }

    const tourData = parseTourData(dataScript.textContent);
    const scenes = Array.isArray(tourData.scenes) ? tourData.scenes : [];

    if (!scenes.length) {
      return;
    }

    applyTheme(container, tourData.theme);
    renderBranding(container, tourData.branding);
    renderAudioControls(container, tourData.audioTracks);
    renderFloorPlans(container, tourData.floorPlans);

    const viewer = new PANOLENS.Viewer({
      container: container.querySelector('.phantomviews-viewer'),
      controlBar: true,
      autoHideInfospot: false,
    });

    const sceneMap = new Map();

    scenes.forEach((scene) => {
      if (!scene || !scene.image_url) {
        return;
      }

      const panorama = new PANOLENS.ImagePanorama(scene.image_url);

      if (Array.isArray(scene.hotspots)) {
        scene.hotspots.forEach((hotspot) => {
          const infospot = new PANOLENS.Infospot(350, hotspot.icon_url || undefined);
          if (hotspot.position) {
            infospot.position.set(hotspot.position.x, hotspot.position.y, hotspot.position.z);
          }

          if (hotspot.type === 'link' && hotspot.target_scene && hotspot.target_scene !== scene.id) {
            infospot.addHoverText(hotspot.title || '');
            infospot.addEventListener('click', () => {
              const target = sceneMap.get(hotspot.target_scene);
              if (target) {
                viewer.setPanorama(target.panorama);
              }
            });
          }

          if (hotspot.type === 'media' && hotspot.media_url) {
            const iframe = document.createElement('iframe');
            iframe.src = hotspot.media_url;
            iframe.setAttribute('allowfullscreen', 'true');
            iframe.classList.add('phantomviews-hotspot-media');
            infospot.addHoverElement(iframe, 220);
          } else if (hotspot.description) {
            infospot.addHoverText(hotspot.description);
          }

          panorama.add(infospot);
        });
      }

      sceneMap.set(scene.id, { panorama, scene });
      viewer.add(panorama);
    });

    const initial = sceneMap.values().next().value;
    if (initial) {
      viewer.setPanorama(initial.panorama);
    }
  });
})();
