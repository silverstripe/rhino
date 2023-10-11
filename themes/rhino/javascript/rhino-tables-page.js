document.addEventListener('DOMContentLoaded', function() {

    function isNumeric(str) {
        if (typeof str != 'string') {
            return false;
        }
        return !isNaN(str) && !isNaN(parseFloat(str))
    }

    function isDate(str) {
        return str.match(/^[0-9]{2}-[0-9]{2}-[0-9]{4}$/);
    }

    function columnType(c) {
        var columnIsNumeric = true;
        var columnIsDate = true;
        var trs = document.querySelectorAll('tbody tr');
        for (var i = 0; i < trs.length; i++) {
            var tr = trs[i];
            var tds = tr.querySelectorAll('td');
            if (tds.length <= c) {
                return '';
            }
            var td = tds[c];
            var str = td.innerHTML;
            if (columnIsNumeric && !isNumeric(str)) {
                columnIsNumeric = false;
            }
            if (columnIsDate && !isDate(str)) {
                columnIsDate = false;
            }
        }
        if (columnIsDate) {
            return { type: 'date', locale: 'en-NZ', format: ['{dd}-{MM}-{yyyy}'] };
        } else if (columnIsNumeric) {
            return 'number';
        }
        return 'string';
    }

    function getFilterColumn(filterName) {
        var colNum = 0;
        for (var th of document.querySelectorAll('th')) {
            if (th.innerText === filterName) {
                return colNum;
            }
            colNum++;
        }
        return null;
    }

    function initTableFilter() {
        var col_types = [];
        for (var c = 0; c < 99; c++) {
            col_types.push(columnType(c));
        }

        var filtersConfig = {
            base_path: '/_resources/themes/rhino/tablefilter/',
            mark_active_columns: true,
            highlight_keywords: true,
            col_types: col_types,
            rows_counter: true,
            alternate_rows: true,
            extensions: [{ name: 'sort' }]
          };
        var tf = new TableFilter('mytable', filtersConfig);
        tf.init();

        var filters = getQuerystringValue('filters');
        if (filters) {
            filters = JSON.parse(filters);
            for (var filterKey of Object.keys(filters)) {
                var column = getFilterColumn(filterKey);
                if (!column) {
                    continue;
                }
                var value = filters[filterKey];
                // remove any potential html tags embeded in json
                var div = document.createElement('div');
                div.innerHTML = value;
                value = div.innerText;
                tf.setFilterValue(column, value);
            }
            tf.filter();
        }
    }

    function getQuerystringValue(key) {
        var arr = window.location.search.replace(/^\?/, '').split('&');
        for (var i = 0; i < arr.length; i++) {
            var a = arr[i].split('=');
            if (a[0] == key) {
                var value = decodeURIComponent(a[1]);
                if (key === 't') {
                    value = value.replace(/[^a-z\-]/g, '');
                }
                return value;
            }
        }
        return '';
    }

    function highlightSelectedTable() {
        var t = getQuerystringValue('t');
        if (!t) {
            return;
        }
        document.querySelector('.table-link[data-table="' + t + '"]').style.fontWeight = 'bold';
    }

    function selectFirstTableIfNoneSelected() {
        var t = getQuerystringValue('t');
        if (t) {
            return;
        }
        document.querySelector('.table-link[data-table]').click();
    }
    
    function init() {
        selectFirstTableIfNoneSelected();
        highlightSelectedTable();
        initTableFilter();
    }

    init();
});
