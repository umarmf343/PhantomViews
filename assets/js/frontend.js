(function () {
const tours = document.querySelectorAll('.phantomviews-tour');
if (!tours.length) {
return;
}

tours.forEach((container) => {
const tourId = container.dataset.tourId;
const dataScript = document.querySelector(`script.phantomviews-data[data-tour-id="${tourId}"]`);
if (!dataScript) {
return;
}

let scenes = [];
try {
scenes = JSON.parse(dataScript.textContent || '[]');
} catch (error) {
console.error('PhantomViews: Failed to parse tour data', error);
return;
}

if (!Array.isArray(scenes) || !scenes.length) {
return;
}

const viewer = new PANOLENS.Viewer({
container: container.querySelector('.phantomviews-viewer'),
controlBar: true,
autoHideInfospot: false,
});

const sceneMap = new Map();

scenes.forEach((scene) => {
const panorama = new PANOLENS.ImagePanorama(scene.image_url);

if (Array.isArray(scene.hotspots)) {
scene.hotspots.forEach((hotspot) => {
const infospot = new PANOLENS.Infospot(350, hotspot.icon_url || undefined);
infospot.position.set(hotspot.position.x, hotspot.position.y, hotspot.position.z);
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
