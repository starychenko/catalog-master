/* Catalog Master Admin JavaScript */

jQuery(document).ready(function($) {
    'use strict';
    
    // Global variables
    var catalogMaster = {
        currentCatalogId: null,
        dataTable: null,
        mappings: [],
        googleHeaders: []
    };
    
    // Initialize
    catalogMaster.init = function() {
        this.bindEvents();
        this.initializeTabs();
        this.initializeDataTable();
        this.initializeColumnStatus();
    };
    
    // Initialize column status on page load
    catalogMaster.initializeColumnStatus = function() {
        // Load existing Google headers from PHP if available
        if (catalog_master_ajax.google_headers && catalog_master_ajax.google_headers.length > 0) {
            catalogMaster.googleHeaders = catalog_master_ajax.google_headers;
        }
        
        // Initialize counters and status on page load
        if ($('#catalog-columns-status').length) {
            catalogMaster.updateColumnStatus();
        }
    };
    
    // Bind events
    catalogMaster.bindEvents = function() {
        // Test Google Sheets connection
        $(document).on('click', '#test-sheets-connection', this.testSheetsConnection);
        
        // Get Google Sheets headers
        $(document).on('click', '#get-sheets-headers', this.getSheetsHeaders);
        
        // Save column mapping
        $(document).on('click', '#save-column-mapping', this.saveColumnMapping);
        
        // Add mapping row
        $(document).on('click', '#add-mapping-row', this.addMappingRow);
        
        // Remove mapping row
        $(document).on('click', '.remove-mapping-btn', this.removeMappingRow);
        
        // Import data
        $(document).on('click', '#import-data', this.importData);
        
        // Export data
        $(document).on('click', '.export-btn', this.exportData);
        
        // Edit item
        $(document).on('click', '.edit-item', this.editItem);
        
        // Delete item
        $(document).on('click', '.delete-item', this.deleteItem);
        
        // Add new item
        $(document).on('click', '#add-new-item', this.addNewItem);
        
        // Modal close
        $(document).on('click', '.catalog-master-modal-close', this.closeModal);
        $(document).on('click', '.catalog-master-modal', function(e) {
            if (e.target === this) {
                catalogMaster.closeModal();
            }
        });
        
        // Form submissions
        $(document).on('submit', '#item-edit-form', this.saveItem);
        
        // Column mapping changes
        $(document).on('change', '.column-mapping-select', function() {
            catalogMaster.updateCatalogColumnStatus();
        });
    };
    
    // Initialize tabs
    catalogMaster.initializeTabs = function() {
        $('.catalog-master-tab-nav a').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            // Remove active class from all tabs and content
            $('.catalog-master-tab-nav a').removeClass('active');
            $('.catalog-master-tab-content').removeClass('active');
            
            // Add active class to clicked tab and corresponding content
            $(this).addClass('active');
            $(target).addClass('active');
        });
    };
    
    // Initialize DataTable
    catalogMaster.initializeDataTable = function() {
        if ($('#catalog-items-table').length && typeof catalog_master_ajax !== 'undefined') {
            var catalogId = $('#catalog-items-table').data('catalog-id');
            if (catalogId) {
                catalogMaster.currentCatalogId = catalogId;
                catalogMaster.dataTable = $('#catalog-items-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: catalog_master_ajax.ajax_url,
                        type: 'POST',
                        data: function(d) {
                            d.action = 'catalog_master_get_catalog_data';
                            d.nonce = catalog_master_ajax.nonce;
                            d.catalog_id = catalogMaster.currentCatalogId;
                        }
                    },
                    columns: [
                        { title: 'ID', width: '50px' },
                        { title: 'Product ID' },
                        { title: 'Name' },
                        { title: 'Price' },
                        { title: 'Qty' },
                        { title: 'Image', orderable: false },
                        { title: 'Sort' },
                        { title: 'Description' },
                        { title: 'Category ID' },
                        { title: 'Category Name' },
                        { title: 'Actions', orderable: false, width: '120px' }
                    ],
                    pageLength: 25,
                    order: [[6, 'asc']], // Sort by sort order
                    language: {
                        processing: 'Завантаження...',
                        search: 'Пошук:',
                        lengthMenu: 'Показати _MENU_ записів',
                        info: 'Показано _START_ до _END_ з _TOTAL_ записів',
                        infoEmpty: 'Показано 0 до 0 з 0 записів',
                        infoFiltered: '(відфільтровано з _MAX_ записів)',
                        paginate: {
                            first: 'Перша',
                            last: 'Остання',
                            next: 'Наступна',
                            previous: 'Попередня'
                        },
                        emptyTable: 'Немає даних для відображення'
                    }
                });
            }
        }
    };
    
    // Test Google Sheets connection
    catalogMaster.testSheetsConnection = function(e) {
        e.preventDefault();
        
        var sheetUrl = $('#google_sheet_url').val();
        var sheetName = $('#sheet_name').val() || 'Sheet1';
        
        if (!sheetUrl) {
            catalogMaster.showMessage('Введіть URL Google Sheets', 'error');
            return;
        }
        
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.html('<span class="catalog-master-spinner"></span>Перевірка...').prop('disabled', true);
        
        $.ajax({
            url: catalog_master_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'catalog_master_test_sheets_connection',
                nonce: catalog_master_ajax.nonce,
                sheet_url: sheetUrl,
                sheet_name: sheetName
            },
            success: function(response) {
                if (response.success) {
                    catalogMaster.showMessage(response.data.message + ' (Знайдено ' + response.data.row_count + ' рядків)', 'success');
                    catalogMaster.googleHeaders = response.data.headers;
                    $('#get-sheets-headers').prop('disabled', false);
                } else {
                    catalogMaster.showMessage(response.data, 'error');
                }
            },
            error: function() {
                catalogMaster.showMessage('Помилка підключення', 'error');
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
            }
        });
    };
    
    // Get Google Sheets headers
    catalogMaster.getSheetsHeaders = function(e) {
        e.preventDefault();
        
        var sheetUrl = $('#google_sheet_url').val();
        var sheetName = $('#sheet_name').val() || 'Sheet1';
        
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.html('<span class="catalog-master-spinner"></span>Завантаження...').prop('disabled', true);
        
        $.ajax({
            url: catalog_master_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'catalog_master_get_sheets_headers',
                nonce: catalog_master_ajax.nonce,
                sheet_url: sheetUrl,
                sheet_name: sheetName
            },
            success: function(response) {
                if (response.success) {
                    catalogMaster.googleHeaders = response.data.headers;
                    catalogMaster.populateColumnMapping();
                    catalogMaster.updateColumnStatus();
                    catalogMaster.showMessage('Заголовки завантажено успішно (' + response.data.headers.length + ' стовпців)', 'success');
                } else {
                    catalogMaster.showMessage(response.data, 'error');
                }
            },
            error: function() {
                catalogMaster.showMessage('Помилка завантаження заголовків', 'error');
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
            }
        });
    };
    
    // Populate column mapping
    catalogMaster.populateColumnMapping = function() {
        var $container = $('#column-mapping-rows');
        var catalogColumns = [
            'product_id', 'product_name', 'product_price', 'product_qty',
            'product_image_url', 'product_sort_order', 'product_description',
            'category_id_1', 'category_id_2', 'category_id_3',
            'category_name_1', 'category_name_2', 'category_name_3',
            'category_image_1', 'category_image_2', 'category_image_3',
            'category_sort_order_1', 'category_sort_order_2', 'category_sort_order_3'
        ];
        
        $container.empty();
        
        catalogColumns.forEach(function(column, index) {
            catalogMaster.addMappingRowForColumn(column, '', index);
        });
        
        $('#save-column-mapping').prop('disabled', false);
    };
    
    // Update visual column status
    catalogMaster.updateColumnStatus = function() {
        // Update Google Sheets columns
        var $googleContainer = $('#google-columns-status');
        $googleContainer.empty();
        
        if (catalogMaster.googleHeaders && catalogMaster.googleHeaders.length > 0) {
            catalogMaster.googleHeaders.forEach(function(header) {
                var $item = $('<div class="column-status-item available" data-column="' + header + '" title="' + header + '">' + header + '</div>');
                $googleContainer.append($item);
            });
            $('#google-total-count').text(catalogMaster.googleHeaders.length);
        } else {
            $googleContainer.html('<div class="column-status-item available">Завантажте заголовки</div>');
            $('#google-total-count').text('0');
        }
        
        // Update mapping status for catalog columns (check current mappings)
        catalogMaster.updateCatalogColumnStatus();
    };
    
    // Update catalog column status based on current mappings
    catalogMaster.updateCatalogColumnStatus = function() {
        console.log('Updating column status...');
        
        var mappedCatalogColumns = {};  // Object to store catalog->google mappings
        var mappedGoogleColumns = [];   // Array of used Google columns
        
        // Get currently mapped columns from the form
        $('#column-mapping-rows .column-mapping-row').each(function() {
            var catalogColumn = $(this).find('.catalog-column').val();
            var googleColumn = $(this).find('.google-column').val();
            
            // Only consider it mapped if BOTH catalog and google columns are selected
            if (catalogColumn && googleColumn) {
                mappedCatalogColumns[catalogColumn] = googleColumn;
                mappedGoogleColumns.push(googleColumn);
            }
        });
        
        console.log('Properly mapped catalog columns:', mappedCatalogColumns);
        console.log('Used Google columns:', mappedGoogleColumns);
        
        // Update catalog columns visual status
        var mappedCount = 0;
        $('#catalog-columns-status .column-status-item').each(function() {
            var $item = $(this);
            var column = $item.data('column');
            
            // Check if this catalog column has a Google Sheets mapping
            if (mappedCatalogColumns.hasOwnProperty(column)) {
                $item.removeClass('unmapped').addClass('mapped');
                // Add visual indicator of which Google column it's mapped to
                $item.attr('data-mapped-to', '→ ' + mappedCatalogColumns[column]);
                mappedCount++;
            } else {
                $item.removeClass('mapped').addClass('unmapped');
                // Remove mapping indicator
                $item.removeAttr('data-mapped-to');
            }
        });
        
        // Update Google Sheets columns visual status (only if we have them)
        if (catalogMaster.googleHeaders && catalogMaster.googleHeaders.length > 0) {
            $('#google-columns-status .column-status-item').each(function() {
                var $item = $(this);
                var column = $item.data('column');
                
                if (mappedGoogleColumns.indexOf(column) !== -1) {
                    $item.removeClass('available').addClass('mapped');
                } else {
                    $item.removeClass('mapped').addClass('available');
                }
            });
        }
        
        // Update counters
        $('#catalog-mapped-count').text(mappedCount);
        
        // Simplified animation - only animate newly mapped items
        $('.column-status-item.mapped').each(function() {
            var $item = $(this);
            if (!$item.hasClass('animated')) {
                $item.addClass('animated');
                $item.css('transform', 'scale(1.05)');
                setTimeout(function() {
                    $item.css('transform', 'scale(1)');
                }, 150);
            }
        });
        
        // Remove animated class from unmapped items
        $('.column-status-item.unmapped').removeClass('animated');
        
        console.log('Status update complete. Mapped count:', mappedCount);
    };
    
    // Add mapping row
    catalogMaster.addMappingRow = function(e) {
        e.preventDefault();
        var index = $('#column-mapping-rows .column-mapping-row').length;
        catalogMaster.addMappingRowForColumn('', '', index);
    };
    
    // Add mapping row for specific column
    catalogMaster.addMappingRowForColumn = function(catalogColumn, googleColumn, index) {
        var googleOptionsHtml = '<option value="">-- Оберіть стовпець --</option>';
        if (catalogMaster.googleHeaders.length > 0) {
            catalogMaster.googleHeaders.forEach(function(header) {
                var selected = header === googleColumn ? 'selected' : '';
                googleOptionsHtml += '<option value="' + header + '" ' + selected + '>' + header + '</option>';
            });
        }
        
        var catalogOptionsHtml = '<option value="">-- Оберіть поле --</option>';
        var catalogColumns = [
            { value: 'product_id', label: 'Product ID' },
            { value: 'product_name', label: 'Product Name' },
            { value: 'product_price', label: 'Product Price' },
            { value: 'product_qty', label: 'Product Quantity' },
            { value: 'product_image_url', label: 'Product Image URL' },
            { value: 'product_sort_order', label: 'Product Sort Order' },
            { value: 'product_description', label: 'Product Description' },
            { value: 'category_id_1', label: 'Category ID 1' },
            { value: 'category_id_2', label: 'Category ID 2' },
            { value: 'category_id_3', label: 'Category ID 3' },
            { value: 'category_name_1', label: 'Category Name 1' },
            { value: 'category_name_2', label: 'Category Name 2' },
            { value: 'category_name_3', label: 'Category Name 3' },
            { value: 'category_image_1', label: 'Category Image 1' },
            { value: 'category_image_2', label: 'Category Image 2' },
            { value: 'category_image_3', label: 'Category Image 3' },
            { value: 'category_sort_order_1', label: 'Category Sort Order 1' },
            { value: 'category_sort_order_2', label: 'Category Sort Order 2' },
            { value: 'category_sort_order_3', label: 'Category Sort Order 3' }
        ];
        
        catalogColumns.forEach(function(col) {
            var selected = col.value === catalogColumn ? 'selected' : '';
            catalogOptionsHtml += '<option value="' + col.value + '" ' + selected + '>' + col.label + '</option>';
        });
        
        var rowHtml = '<div class="column-mapping-row">' +
            '<select class="column-mapping-select google-column" name="mappings[' + index + '][google_column]">' + googleOptionsHtml + '</select>' +
            '<select class="column-mapping-select catalog-column" name="mappings[' + index + '][catalog_column]">' + catalogOptionsHtml + '</select>' +
            '<button type="button" class="remove-mapping-btn">Видалити</button>' +
            '</div>';
        
        $('#column-mapping-rows').append(rowHtml);
        
        // Update status after adding new row
        catalogMaster.updateCatalogColumnStatus();
    };
    
    // Remove mapping row
    catalogMaster.removeMappingRow = function(e) {
        e.preventDefault();
        $(this).closest('.column-mapping-row').remove();
        
        // Update status after removing
        catalogMaster.updateCatalogColumnStatus();
    };
    
    // Save column mapping
    catalogMaster.saveColumnMapping = function(e) {
        e.preventDefault();
        
        var catalogId = $(this).data('catalog-id');
        var mappings = [];
        
        $('#column-mapping-rows .column-mapping-row').each(function() {
            var googleColumn = $(this).find('.google-column').val();
            var catalogColumn = $(this).find('.catalog-column').val();
            
            if (googleColumn && catalogColumn) {
                mappings.push({
                    google_column: googleColumn,
                    catalog_column: catalogColumn
                });
            }
        });
        
        if (mappings.length === 0) {
            catalogMaster.showMessage('Додайте хоча б одну відповідність стовпців', 'warning');
            return;
        }
        
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.html('<span class="catalog-master-spinner"></span>Збереження...').prop('disabled', true);
        
        $.ajax({
            url: catalog_master_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'catalog_master_save_column_mapping',
                nonce: catalog_master_ajax.nonce,
                catalog_id: catalogId,
                mappings: mappings
            },
            success: function(response) {
                if (response.success) {
                    catalogMaster.showMessage('Відповідність стовпців збережено', 'success');
                    catalogMaster.updateCatalogColumnStatus();
                    $('#import-data').prop('disabled', false);
                } else {
                    catalogMaster.showMessage(response.data, 'error');
                }
            },
            error: function() {
                catalogMaster.showMessage('Помилка збереження', 'error');
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
            }
        });
    };
    
    // Import data
    catalogMaster.importData = function(e) {
        e.preventDefault();
        
        var catalogId = $(this).data('catalog-id');
        var $btn = $(this);
        var originalText = $btn.text();
        
        $btn.html('<span class="catalog-master-spinner"></span>Імпорт...').prop('disabled', true);
        
        // Show progress
        var $progress = $('<div class="import-progress">' +
            '<div>Імпорт даних з Google Sheets...</div>' +
            '<div class="import-progress-bar"><div class="import-progress-fill"></div></div>' +
            '</div>');
        $btn.after($progress);
        
        // Animate progress
        setTimeout(function() {
            $progress.find('.import-progress-fill').css('width', '50%');
        }, 500);
        
        $.ajax({
            url: catalog_master_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'catalog_master_import_data',
                nonce: catalog_master_ajax.nonce,
                catalog_id: catalogId
            },
            success: function(response) {
                $progress.find('.import-progress-fill').css('width', '100%');
                
                setTimeout(function() {
                    if (response.success) {
                        catalogMaster.showMessage(response.data.message, 'success');
                        
                        // Refresh DataTable if exists
                        if (catalogMaster.dataTable) {
                            catalogMaster.dataTable.ajax.reload();
                        }
                    } else {
                        catalogMaster.showMessage(response.data, 'error');
                    }
                    $progress.remove();
                }, 1000);
            },
            error: function() {
                catalogMaster.showMessage('Помилка імпорту', 'error');
                $progress.remove();
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
            }
        });
    };
    
    // Export data
    catalogMaster.exportData = function(e) {
        e.preventDefault();
        
        var catalogId = $(this).data('catalog-id');
        var format = $(this).data('format');
        
        $.ajax({
            url: catalog_master_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'catalog_master_export',
                nonce: catalog_master_ajax.nonce,
                catalog_id: catalogId,
                format: format
            },
            success: function(response) {
                if (response.success) {
                    // Determine which URL to open based on format
                    var urlToOpen;
                    var isFileDownload = false;
                    
                    if (format === 'csv' || format === 'excel') {
                        // Files for download - use download URL
                        urlToOpen = response.data.download_url;
                        isFileDownload = true;
                    } else if (format === 'json' || format === 'xml') {
                        // Web feeds - use feed URL
                        urlToOpen = response.data.feed_url;
                        isFileDownload = false;
                    } else {
                        // Fallback - use download URL
                        urlToOpen = response.data.download_url;
                        isFileDownload = true;
                    }
                    
                    // Open appropriate URL
                    window.open(urlToOpen);
                    
                    // Show feed URL for reference (always useful)
                    var feedHtml = '<div class="catalog-master-status info">' +
                        '<strong>Feed URL:</strong> <a href="' + response.data.feed_url + '" target="_blank">' + response.data.feed_url + '</a>';
                    
                    // For file downloads, also show download URL
                    if (isFileDownload) {
                        feedHtml += '<br><strong>Download URL:</strong> <a href="' + response.data.download_url + '" target="_blank">' + response.data.download_url + '</a>';
                    }
                    
                    feedHtml += '</div>';
                    
                    // Remove previous status messages of this type
                    $('.catalog-master-status.info').remove();
                    $('.export-options').after(feedHtml);
                } else {
                    catalogMaster.showMessage(response.data, 'error');
                }
            },
            error: function() {
                catalogMaster.showMessage('Помилка експорту', 'error');
            }
        });
    };
    
    // Edit item
    catalogMaster.editItem = function(e) {
        e.preventDefault();
        var itemId = $(this).data('id');
        // Implementation for editing item would go here
        catalogMaster.showMessage('Функція редагування в розробці', 'info');
    };
    
    // Delete item
    catalogMaster.deleteItem = function(e) {
        e.preventDefault();
        
        if (!confirm('Ви впевнені, що хочете видалити цей запис?')) {
            return;
        }
        
        var itemId = $(this).data('id');
        
        $.ajax({
            url: catalog_master_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'catalog_master_delete_item',
                nonce: catalog_master_ajax.nonce,
                item_id: itemId
            },
            success: function(response) {
                if (response.success) {
                    catalogMaster.showMessage('Запис видалено', 'success');
                    if (catalogMaster.dataTable) {
                        catalogMaster.dataTable.ajax.reload();
                    }
                } else {
                    catalogMaster.showMessage(response.data, 'error');
                }
            },
            error: function() {
                catalogMaster.showMessage('Помилка видалення', 'error');
            }
        });
    };
    
    // Add new item
    catalogMaster.addNewItem = function(e) {
        e.preventDefault();
        // Implementation for adding new item would go here
        catalogMaster.showMessage('Функція додавання в розробці', 'info');
    };
    
    // Close modal
    catalogMaster.closeModal = function() {
        $('.catalog-master-modal').hide();
    };
    
    // Save item
    catalogMaster.saveItem = function(e) {
        e.preventDefault();
        // Implementation for saving item would go here
        catalogMaster.showMessage('Функція збереження в розробці', 'info');
    };
    
    // Show message
    catalogMaster.showMessage = function(message, type) {
        var $message = $('<div class="catalog-master-status ' + type + '">' + message + '</div>');
        
        // Remove existing messages
        $('.catalog-master-status').remove();
        
        // Add new message
        $('.wrap h1').after($message);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $message.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Scroll to top
        $('html, body').animate({ scrollTop: 0 }, 500);
    };
    
    // Initialize everything
    catalogMaster.init();
}); 