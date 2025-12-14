// Tailwind CSS
import './styles/app.css';

// jQuery
import $ from 'jquery';
window.$ = window.jQuery = $;

// DataTables JS + CSS
import dt from 'datatables.net';
import 'datatables.net-dt/css/dataTables.dataTables.css'; // correct CSS for v2+

// Attach DataTables to jQuery
dt(window, $);

// Initialize minimal DataTable
$(document).ready(function () {
    console.log('jQuery:', $);               // should log jQuery function
    console.log('DataTable function:', $.fn.DataTable); // should now be a function

    $('.datatable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true
    });
});
