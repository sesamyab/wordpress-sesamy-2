// Importing the bootstrap module is enough — its IIFE auto-runs on load
// and reads its options from the inline `<script id="sesamy-js">` config
// element the WordPress plugin renders in `<head>`. No explicit call is
// needed here; calling sesamyBootstrap a second time would duplicate the
// script-chain injection.
// eslint-disable-next-line import/no-unresolved
import '@sesamy/sesamy-js/bootstrap';
