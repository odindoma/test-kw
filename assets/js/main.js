// Основной JavaScript файл для анализа рекламных кампаний

document.addEventListener('DOMContentLoaded', function() {
    // Инициализация тултипов Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Инициализация поповеров Bootstrap
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Автоматическое скрытие алертов через 5 секунд
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Инициализация функций загрузки файлов
    initFileUpload();
    
    // Инициализация фильтров
    initFilters();
    
    // Инициализация графиков
    initCharts();
});

/**
 * Инициализация загрузки файлов
 */
function initFileUpload() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('csvFile');
    const uploadForm = document.getElementById('uploadForm');
    
    if (!uploadArea || !fileInput) return;

    // Обработка drag & drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            updateFileInfo(files[0]);
        }
    });

    // Обработка клика по области загрузки
    uploadArea.addEventListener('click', function() {
        fileInput.click();
    });

    // Обработка выбора файла
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            updateFileInfo(this.files[0]);
        }
    });

    // Обработка отправки формы
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            uploadFile();
        });
    }
}

/**
 * Обновление информации о выбранном файле
 */
function updateFileInfo(file) {
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (fileInfo && fileName && fileSize && uploadBtn) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        fileInfo.style.display = 'block';
        uploadBtn.disabled = false;
    }
}

/**
 * Форматирование размера файла
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Загрузка файла
 */
function uploadFile() {
    const formData = new FormData();
    const fileInput = document.getElementById('csvFile');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (!fileInput.files[0]) {
        showAlert('Пожалуйста, выберите файл для загрузки', 'warning');
        return;
    }
    
    formData.append('csv_file', fileInput.files[0]);
    
    // Показываем прогресс-бар
    if (progressContainer) {
        progressContainer.style.display = 'block';
    }
    
    // Отключаем кнопку загрузки
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Загрузка...';
    }
    
    // Отправляем файл
    fetch('api/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`Файл успешно загружен! Импортировано записей: ${data.imported}`, 'success');
            
            // Обновляем статистику на странице
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showAlert('Ошибка загрузки: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        showAlert('Произошла ошибка при загрузке файла', 'danger');
    })
    .finally(() => {
        // Скрываем прогресс-бар
        if (progressContainer) {
            progressContainer.style.display = 'none';
        }
        
        // Включаем кнопку загрузки
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i>Загрузить файл';
        }
    });
}

/**
 * Инициализация фильтров
 */
function initFilters() {
    const filterForm = document.getElementById('filterForm');
    const resetBtn = document.getElementById('resetFilters');
    
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            applyFilters();
        });
    }
    
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            resetFilters();
        });
    }
    
    // Автоматическое применение фильтров при изменении
    const filterInputs = document.querySelectorAll('.auto-filter');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            applyFilters();
        });
    });
}

/**
 * Применение фильтров
 */
function applyFilters() {
    const formData = new FormData(document.getElementById('filterForm'));
    const params = new URLSearchParams(formData);
    
    // Показываем индикатор загрузки
    showLoadingOverlay();
    
    // Обновляем URL с параметрами фильтра
    const newUrl = window.location.pathname + '?' + params.toString();
    window.history.pushState({}, '', newUrl);
    
    // Перезагружаем страницу с новыми параметрами
    location.reload();
}

/**
 * Сброс фильтров
 */
function resetFilters() {
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.reset();
        
        // Удаляем параметры из URL
        window.history.pushState({}, '', window.location.pathname);
        
        // Перезагружаем страницу
        location.reload();
    }
}

/**
 * Инициализация графиков
 */
function initCharts() {
    // Инициализация графика роста кампаний
    initCampaignGrowthChart();
    
    // Инициализация круговой диаграммы уникальности
    initUniquenessChart();
    
    // Инициализация графика активности страниц
    initPageActivityChart();
}

/**
 * График роста кампаний
 */
function initCampaignGrowthChart() {
    const chartElement = document.getElementById('campaignGrowthChart');
    if (!chartElement) return;
    
    // Получаем данные для графика
    fetch('api/chart_data.php?type=campaign_growth')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderCampaignGrowthChart(chartElement, data.data);
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки данных графика:', error);
        });
}

/**
 * Отрисовка графика роста кампаний
 */
function renderCampaignGrowthChart(element, data) {
    // Используем Chart.js для отрисовки
    const ctx = element.getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => item.date),
            datasets: [{
                label: 'Новые кампании',
                data: data.map(item => item.daily_new_campaigns),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }, {
                label: 'Всего активных',
                data: data.map(item => item.daily_total_campaigns),
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Рост кампаний по дням'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

/**
 * Показать алерт
 */
function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer') || document.body;
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.insertBefore(alertDiv, alertContainer.firstChild);
    
    // Автоматически скрыть через 5 секунд
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alertDiv);
        bsAlert.close();
    }, 5000);
}

/**
 * Показать оверлей загрузки
 */
function showLoadingOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.id = 'loadingOverlay';
    overlay.innerHTML = `
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
            <div class="mt-2">Загрузка данных...</div>
        </div>
    `;
    
    document.body.appendChild(overlay);
}

/**
 * Скрыть оверлей загрузки
 */
function hideLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

/**
 * Утилиты для работы с данными
 */
const Utils = {
    /**
     * Форматирование числа с разделителями тысяч
     */
    formatNumber: function(num) {
        return new Intl.NumberFormat('ru-RU').format(num);
    },
    
    /**
     * Форматирование даты
     */
    formatDate: function(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('ru-RU');
    },
    
    /**
     * Форматирование даты и времени
     */
    formatDateTime: function(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('ru-RU');
    },
    
    /**
     * Обрезка текста
     */
    truncateText: function(text, maxLength = 100) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    },
    
    /**
     * Экранирование HTML
     */
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Экспорт утилит в глобальную область видимости
window.Utils = Utils;

