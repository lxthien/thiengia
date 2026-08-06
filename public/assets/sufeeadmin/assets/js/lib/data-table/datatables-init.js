(function ($) {
    $('#bootstrap-data-table').DataTable({
        order: [],
        searching: false,
        lengthMenu: [[50, 100, 150, -1], [50, 100, 150, "All"]],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.10.19/i18n/Vietnamese.json'
        }
    });

    $('#bootstrap-data-table-export').DataTable({
        dom: 'lBfrtip',
        lengthMenu: [[50, 100, 150, -1], [50, 100, 150, "All"]],
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
    
    $('#row-select').DataTable( {
        initComplete: function () {
            this.api().columns().every( function () {
                var column = this;
                var select = $('<select class="form-control"><option value=""></option></select>')
                    .appendTo( $(column.footer()).empty() )
                    .on( 'change', function () {
                        var val = $.fn.dataTable.util.escapeRegex(
                            $(this).val()
                        );
    
                        column
                            .search( val ? '^'+val+'$' : '', true, false )
                            .draw();
                    } );
    
                column.data().unique().sort().each( function ( d, j ) {
                    select.append( '<option value="'+d+'">'+d+'</option>' )
                } );
            } );
        }
    });






})(jQuery);
