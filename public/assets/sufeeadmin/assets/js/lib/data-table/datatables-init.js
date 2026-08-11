(function ($) {
    // Các trang admin render sẵn 1 hàng "chưa có dữ liệu" (<td colspan="N">) khi bảng
    // rỗng — DataTables đếm cột theo <thead> rồi cố đọc từng ô theo index, hàng colspan
    // chỉ có 1 <td> nên bị lệch số cột, ra lỗi "Requested unknown parameter '1' for row 0".
    // Bỏ qua khởi tạo DataTables cho bảng nào chỉ có hàng placeholder này — không mất gì
    // vì 1 bảng rỗng cũng chẳng cần sort/phân trang.
    function hasRealRows($table) {
        var $rows = $table.find('tbody tr');
        if ($rows.length === 0) {
            return false;
        }
        if ($rows.length === 1 && $rows.find('td[colspan]').length > 0) {
            return false;
        }
        return true;
    }

    var $mainTable = $('#bootstrap-data-table');
    if ($mainTable.length && hasRealRows($mainTable)) {
        $mainTable.DataTable({
            order: [],
            searching: false,
            lengthMenu: [[50, 100, 150, -1], [50, 100, 150, "All"]],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.10.19/i18n/Vietnamese.json'
            }
        });
    }

    var $exportTable = $('#bootstrap-data-table-export');
    if ($exportTable.length && hasRealRows($exportTable)) {
        $exportTable.DataTable({
            dom: 'lBfrtip',
            lengthMenu: [[50, 100, 150, -1], [50, 100, 150, "All"]],
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ]
        });
    }

    var $rowSelect = $('#row-select');
    if ($rowSelect.length && hasRealRows($rowSelect)) {
        $rowSelect.DataTable( {
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
    }


})(jQuery);
