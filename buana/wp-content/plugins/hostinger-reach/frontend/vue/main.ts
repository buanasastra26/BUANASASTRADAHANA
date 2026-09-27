import { HButton, HCard, HCheckbox, HIcon, HSnackbar, HText, HToast, useTheme } from '@hostinger/hcomponents';
import { createPinia } from 'pinia';
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate';
import { createApp } from 'vue';

import './styles/main.scss';

import App from './App.vue';
import { setDirectives } from './directives';
import router from './router';

const initializeVueApp = () => {
	const app = createApp(App);
	const pinia = createPinia();
	const { setThemeOnly } = useTheme();

	setThemeOnly('base');
	pinia.use(piniaPluginPersistedstate);

	app.use(router);
	app.use(pinia);

	app.component('HButton', HButton);
	app.component('HCard', HCard);
	app.component('HIcon', HIcon);
	app.component('HSnackbar', HSnackbar);
	app.component('HText', HText);
	app.component('HToast', HToast);
	app.component('HCheckbox', HCheckbox);

	setDirectives(app);

	app.mount('#hostinger-reach-app');
};

document.addEventListener('DOMContentLoaded', () => {
	const targetElement = document.getElementById('hostinger-reach-app');

	if (targetElement) {
		initializeVueApp();
	}
});
