(function (wp) {
const { apiFetch, components, element, i18n } = wp;
const { useState, useEffect } = element;
const { Button, Card, CardBody, CardHeader, Flex, FlexItem, Notice, Spinner, TextControl, TextareaControl, Panel, PanelBody } = components;
const { __ } = i18n;

function LicensePanel({ licenseState, proEnabled, licenseExpiry, onLicenseUpdate }) {
const [licenseKey, setLicenseKey] = useState('');
const [isSaving, setIsSaving] = useState(false);
const [message, setMessage] = useState(null);

const activateLicense = () => {
setIsSaving(true);
setMessage(null);

apiFetch({
path: 'phantomviews/v1/license/activate',
method: 'POST',
data: { license_key: licenseKey },
})
.then((response) => {
setMessage({ status: 'success', text: response.message });
onLicenseUpdate(response);
})
.catch((error) => {
setMessage({ status: 'error', text: error.message || __('Activation failed', 'phantomviews') });
})
.finally(() => setIsSaving(false));
};

const deactivateLicense = () => {
setIsSaving(true);
apiFetch({ path: 'phantomviews/v1/license/deactivate', method: 'POST' })
.then((response) => {
setMessage({ status: 'success', text: response.message });
onLicenseUpdate(response);
})
.catch((error) => {
setMessage({ status: 'error', text: error.message || __('Deactivation failed', 'phantomviews') });
})
.finally(() => setIsSaving(false));
};

return element.createElement(
Card,
{ className: 'phantomviews-license-card' },
element.createElement(CardHeader, null, __('PhantomViews License', 'phantomviews')),
element.createElement(
CardBody,
null,
element.createElement(
'div',
{ className: 'phantomviews-license-status' },
element.createElement('span', { className: 'status-dot ' + licenseState }),
element.createElement('span', { className: 'status' }, licenseState),
proEnabled && licenseExpiry
? element.createElement('span', null, __('Expires', 'phantomviews') + ': ' + licenseExpiry)
: null
),
message
? element.createElement(Notice, { status: message.status, isDismissible: true }, message.text)
: null,
element.createElement(TextControl, {
label: __('License Key', 'phantomviews'),
value: licenseKey,
onChange: setLicenseKey,
placeholder: __('Enter your license key', 'phantomviews'),
disabled: isSaving,
}),
element.createElement(
Flex,
{ justify: 'flex-start', gap: '10px' },
element.createElement(
FlexItem,
null,
element.createElement(
Button,
{
variant: 'primary',
onClick: activateLicense,
disabled: isSaving || !licenseKey,
},
isSaving ? element.createElement(Spinner, null) : __('Activate', 'phantomviews')
)
),
proEnabled
? element.createElement(
FlexItem,
null,
element.createElement(
Button,
{
variant: 'secondary',
onClick: deactivateLicense,
disabled: isSaving,
},
__('Deactivate', 'phantomviews')
)
)
: null
)
)
);
}

function HotspotEditor({ scene, onUpdate }) {
const [localScene, setLocalScene] = useState(scene);

useEffect(() => setLocalScene(scene), [scene]);

const updateHotspot = (index, field, value) => {
const updated = { ...localScene };
updated.hotspots = [...(updated.hotspots || [])];
updated.hotspots[index] = { ...updated.hotspots[index], [field]: value };
setLocalScene(updated);
onUpdate(updated);
};

const addHotspot = () => {
const updated = { ...localScene };
updated.hotspots = [...(updated.hotspots || []), { title: '', type: 'info', position: { x: 0, y: 0, z: 0 } }];
setLocalScene(updated);
onUpdate(updated);
};

return element.createElement(
Panel,
null,
element.createElement(
PanelBody,
{ title: __('Hotspots', 'phantomviews'), initialOpen: true },
(element.createElement(
Button,
{ variant: 'secondary', onClick: addHotspot },
__('Add Hotspot', 'phantomviews')
)),
(localScene.hotspots || []).map((hotspot, index) =>
element.createElement(
'fieldset',
{ key: index, className: 'phantomviews-hotspot-fieldset' },
element.createElement('legend', null, __('Hotspot', 'phantomviews') + ' #' + (index + 1)),
element.createElement(TextControl, {
label: __('Title', 'phantomviews'),
value: hotspot.title || '',
onChange: (value) => updateHotspot(index, 'title', value),
}),
element.createElement(TextControl, {
label: __('Type', 'phantomviews'),
value: hotspot.type || 'info',
onChange: (value) => updateHotspot(index, 'type', value),
help: __('Supported: link, info, media, url', 'phantomviews'),
}),
element.createElement(TextControl, {
label: __('Target Scene', 'phantomviews'),
value: hotspot.target_scene || '',
onChange: (value) => updateHotspot(index, 'target_scene', value),
}),
element.createElement(TextareaControl, {
label: __('Description', 'phantomviews'),
value: hotspot.description || '',
onChange: (value) => updateHotspot(index, 'description', value),
}),
element.createElement(TextControl, {
label: __('Media URL', 'phantomviews'),
value: hotspot.media_url || '',
onChange: (value) => updateHotspot(index, 'media_url', value),
}),
)
)
)
);
}

function SceneCard({ scene, onUpdate, onDelete, isPro }) {
const [localScene, setLocalScene] = useState(scene);

useEffect(() => setLocalScene(scene), [scene]);

const updateField = (field, value) => {
const updated = { ...localScene, [field]: value };
setLocalScene(updated);
onUpdate(updated);
};

return element.createElement(
Card,
{ className: 'phantomviews-scene-card' },
element.createElement(CardHeader, null, localScene.title || __('Untitled Scene', 'phantomviews')),
element.createElement(
CardBody,
null,
element.createElement(TextControl, {
label: __('Scene Title', 'phantomviews'),
value: localScene.title,
onChange: (value) => updateField('title', value),
}),
element.createElement(TextControl, {
label: __('Image URL', 'phantomviews'),
value: localScene.image_url,
onChange: (value) => updateField('image_url', value),
placeholder: __('Select or upload a 360° image', 'phantomviews'),
}),
element.createElement(HotspotEditor, {
scene: localScene,
onUpdate,
}),
element.createElement(
Flex,
{ className: 'phantomviews-scene-actions' },
element.createElement(
Button,
{ variant: 'secondary', onClick: () => onDelete(localScene.id) },
__('Delete Scene', 'phantomviews')
)
)
)
);
}

function TourEditor({ postId, initialScenes, sceneLimit }) {
const [scenes, setScenes] = useState(initialScenes || []);
const [isSaving, setIsSaving] = useState(false);
const [notice, setNotice] = useState(null);

const addScene = () => {
if (!PhantomViewsAdmin.pro_enabled && scenes.length >= sceneLimit) {
setNotice({ status: 'warning', text: PhantomViewsAdmin.i18n.sceneLimitReached });
return;
}

setScenes([
...scenes,
{
id: 'scene-' + Date.now(),
title: '',
image_url: '',
hotspots: [],
},
]);
};

const updateScene = (updatedScene) => {
setScenes((prev) => prev.map((scene) => (scene.id === updatedScene.id ? updatedScene : scene)));
};

const deleteScene = (sceneId) => {
setScenes((prev) => prev.filter((scene) => scene.id !== sceneId));
};

const saveScenes = () => {
setIsSaving(true);
setNotice(null);

apiFetch({
path: `phantomviews/v1/tours/${postId}`,
method: 'POST',
data: { scenes },
})
.then((response) => {
setNotice({ status: 'success', text: response.message });
})
.catch((error) => {
setNotice({ status: 'error', text: error.message || __('Failed to save scenes', 'phantomviews') });
})
.finally(() => setIsSaving(false));
};

return element.createElement(
'div',
{ className: 'phantomviews-tour-editor' },
notice ? element.createElement(Notice, { status: notice.status }, notice.text) : null,
element.createElement(
Flex,
{ justify: 'flex-end' },
element.createElement(
Button,
{ variant: 'primary', onClick: saveScenes, disabled: isSaving },
isSaving ? element.createElement(Spinner, null) : PhantomViewsAdmin.i18n.save
)
),
element.createElement(
Button,
{ onClick: addScene, variant: 'secondary' },
__('Add Scene', 'phantomviews')
),
element.createElement(
'div',
{ className: 'phantomviews-scene-list' },
scenes.map((scene) =>
element.createElement(SceneCard, {
key: scene.id,
scene,
onUpdate: updateScene,
onDelete: deleteScene,
isPro: PhantomViewsAdmin.pro_enabled,
})
)
)
);
}

document.addEventListener('DOMContentLoaded', () => {
const root = document.getElementById('phantomviews-tour-root');
if (root) {
const postId = root.dataset.postId;
const initialScenes = JSON.parse(root.dataset.scenes || '[]');
const sceneLimit = parseInt(root.dataset.sceneLimit || '3', 10);
element.render(element.createElement(TourEditor, { postId, initialScenes, sceneLimit }), root);
}

const licenseRoot = document.getElementById('phantomviews-license-root');
if (licenseRoot) {
element.render(
element.createElement(LicensePanel, {
licenseState: PhantomViewsAdmin.license_state,
proEnabled: PhantomViewsAdmin.pro_enabled,
licenseExpiry: PhantomViewsAdmin.license_expiry,
onLicenseUpdate: () => window.location.reload(),
}),
licenseRoot
);
}
});
})(window.wp || {});
