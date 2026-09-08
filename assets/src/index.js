/**
 * Entry point. Mounts the dashboard into the admin page container.
 */

import { createRoot } from '@wordpress/element';
import App from './components/App';
import './dashboard.scss';

const container = document.getElementById( 'modern-dashboard-root' );

if ( container ) {
	container.textContent = '';
	createRoot( container ).render( <App /> );
}
