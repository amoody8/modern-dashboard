/**
 * Entry point for the admin shell.
 *
 * Renders our sidebar and header into a mount printed by Shell.php, and hides
 * core's. The page content below is untouched — every admin screen, including
 * ones from plugins we have never seen, renders exactly as it always did.
 */

import { createRoot, StrictMode } from '@wordpress/element';
import Sidebar from './shell/Sidebar';
import Topbar from './shell/Topbar';
import './shell.scss';

const boot = window.modernDashboardShell;
const container = document.getElementById( 'mds-shell-root' );

if ( boot && container ) {
	createRoot( container ).render(
		<StrictMode>
			<Sidebar
				items={ boot.menu }
				current={ boot.current }
				siteName={ boot.siteName }
				links={ boot.links }
				isNetwork={ boot.isNetwork }
				user={ boot.user }
			/>
			<Topbar
				title={ boot.title }
				user={ boot.user }
				links={ boot.links }
				siteName={ boot.siteName }
			/>
		</StrictMode>
	);
}
