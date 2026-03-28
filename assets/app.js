// Tailwind CSS
import './styles/app.css';
// import './styles/rain.css';

// jQuery
import $ from 'jquery';
window.$ = window.jQuery = $;

// DataTables JS + CSS
import dt from 'datatables.net';
import 'datatables.net-dt/css/dataTables.dataTables.css';

// Attach DataTables to jQuery
dt(window, $);

// Initialize
$(document).ready(function () {
    $('.datatable').DataTable({
        paging:    true,
        searching: true,
        ordering:  true,
        info:      true
    });
});