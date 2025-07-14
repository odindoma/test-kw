// Основной JavaScript файл для анализа рекламных кампаний - ИСПРАВЛЕННАЯ ВЕРСИЯ

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
 * Инициализация загрузки файлов - ИСПРАВЛЕННАЯ ВЕРСИЯ
 */
function initFileUpload() {
    console.log('Инициализация загрузки файлов...');
    
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('csvFile');
    const uploadForm = document.getElementById('uploadForm');

    if (!uploadArea || !fileInput) {
        console.log('Элементы загрузки не найдены на этой странице');
        return;
    }

    console.log('Элементы загрузки найдены:', { uploadArea, fileInput });

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
            console.log('Файл перетащен:', files[0]);
            fileInput.files = files;
            updateFileInfo(files[0]);
        }
    });

    // Обработка клика по области загрузки
    uploadArea.addEventListener('click', function() {
        console.log('Клик по области загрузки');
        fileInput.click();
    });

    // Обработка выбора файла - ИСПРАВЛЕНО
    fileInput.addEventListener('change', function(e) {
        console.log('Файл выбран через диалог:', this.files);
        if (this.files.length > 0) {
            console.log('Обновляем информацию о файле:', this.files[0]);
            updateFileInfo(this.files[0]);
        } else {
            console.log('Файл не выбран, скрываем информацию');
            hideFileInfo();
        }
    });

    // Обработка отправки формы
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Отправка формы загрузки');
            uploadFile();
        });
    }

    // Обработчик кнопки сброса фильтров
    const resetFiltersBtn = document.getElementById('resetFilters');
    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', function() {
            window.location.href = window.location.pathname;
        });
    }
}

/**
 * Обновление информации о выбранном файле - ИСПРАВЛЕННАЯ ВЕРСИЯ
 */
function updateFileInfo(file) {
    console.log('updateFileInfo вызвана с файлом:', file);
    
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const uploadBtn = document.getElementById('uploadBtn');
    
    console.log('Найденные элементы:', { fileInfo, fileName, fileSize, uploadBtn });

    if (!fileInfo || !fileName || !fileSize || !uploadBtn) {
        console.error('Не все элементы найдены для отображения информации о файле');
        return;
    }

    // Проверяем тип файла
    if (!file.name.toLowerCase().endsWith('.csv')) {
        console.warn('Выбран не CSV файл:', file.name);
        showAlert('Пожалуйста, выберите CSV файл', 'warning');
        hideFileInfo();
        return;
    }

    // Проверяем размер файла (максимум 50MB)
    const maxSize = 50 * 1024 * 1024; // 50MB
    if (file.size > maxSize) {
        console.warn('Файл слишком большой:', file.size);
        showAlert('Файл слишком большой. Максимальный размер: 50MB', 'warning');
        hideFileInfo();
        return;
    }

    try {
        // Устанавливаем информацию о файле
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        
        // Показываем блок с информацией
        fileInfo.style.display = 'block';
        fileInfo.style.visibility = 'visible';
        fileInfo.classList.remove('d-none');
        
        // Активируем кнопку загрузки
        uploadBtn.disabled = false;
        uploadBtn.classList.remove('disabled');
        
        console.log('Информация о файле обновлена успешно');
        console.log('fileInfo display:', fileInfo.style.display);
        console.log('fileInfo visibility:', fileInfo.style.visibility);
        
        // Добавляем небольшую задержку для отладки
        setTimeout(() => {
            console.log('Проверка через 100ms - fileInfo display:', fileInfo.style.display);
            console.log('Проверка через 100ms - fileInfo visibility:', fileInfo.style.visibility);
        }, 100);
        
    } catch (error) {
        console.error('Ошибка при обновлении информации о файле:', error);
    }
}

/**
 * Скрытие информации о файле
 */
function hideFileInfo() {
    console.log('hideFileInfo вызвана');
    
    const fileInfo = document.getElementById('fileInfo');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (fileInfo) {
        fileInfo.style.display = 'none';
        fileInfo.classList.add('d-none');
    }
    
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.classList.add('disabled');
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
 * Загрузка файла - УЛУЧШЕННАЯ ВЕРСИЯ
 */
function uploadFile() {
    console.log('Начало загрузки файла');
    
    const formData = new FormData();
    const fileInput = document.getElementById('csvFile');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (!fileInput || !fileInput.files[0]) {
        showAlert('Пожалуйста, выберите файл для загрузки', 'warning');
        return;
    }
    
    const file = fileInput.files[0];
    formData.append('csv_file', file);
    
    // Показываем прогресс-бар
    if (progressContainer) {
        progressContainer.style.display = 'block';
    }
    
    // Отключаем кнопку загрузки
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Загрузка...';
    }
    
    // Создаем XMLHttpRequest для отслеживания прогресса
    const xhr = new XMLHttpRequest();
    
    // Обработчик прогресса загрузки
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable && progressBar) {
            const percentComplete = (e.loaded / e.total) * 100;
            progressBar.style.width = percentComplete + '%';
            progressBar.setAttribute('aria-valuenow', percentComplete);
            progressBar.textContent = Math.round(percentComplete) + '%';
        }
    });
    
    // Обработчик завершения загрузки
    xhr.addEventListener('load', function() {
        console.log('Загрузка завершена, статус:', xhr.status);
        
        if (progressContainer) {
            progressContainer.style.display = 'none';
        }
        
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i>Загрузить файл';
        }
        
        try {
            const response = JSON.parse(xhr.responseText);
            console.log('Ответ сервера:', response);
            
            if (response.success) {
                showAlert(response.message || 'Файл успешно загружен!', 'success');
                
                // Очищаем форму
                if (fileInput) {
                    fileInput.value = '';
                }
                hideFileInfo();
                
                // Перенаправляем на страницу аналитики через 2 секунды
                setTimeout(() => {
                    window.location.href = 'analytics.php';
                }, 2000);
                
            } else {
                showAlert(response.message || 'Ошибка при загрузке файла', 'danger');
            }
        } catch (error) {
            console.error('Ошибка парсинга ответа:', error);
            showAlert('Ошибка при обработке ответа сервера', 'danger');
        }
    });
    
    // Обработчик ошибок
    xhr.addEventListener('error', function() {
        console.error('Ошибка загрузки файла');
        
        if (progressContainer) {
            progressContainer.style.display = 'none';
        }
        
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i>Загрузить файл';
        }
        
        showAlert('Произошла ошибка при загрузке файла', 'danger');
    });
    
    // Отправляем запрос
    xhr.open('POST', '../api/upload.php');
    xhr.send(formData);
}

/**
 * Показ уведомления
 */
function showAlert(message, type = 'info') {
    console.log('Показываем уведомление:', message, type);
    
    // Удаляем существующие алерты
    const existingAlerts = document.querySelectorAll('.alert.dynamic-alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Создаем новый алерт
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show dynamic-alert`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Вставляем алерт в начало контейнера
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
    }
    
    // Автоматически скрываем через 5 секунд
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

/**
 * Инициализация фильтров
 */
function initFilters() {
    // Обработчик кнопки сброса фильтров
    const resetBtn = document.getElementById('resetFilters');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            // Очищаем все поля формы
            const form = this.closest('form');
            if (form) {
                form.reset();
                // Перенаправляем на страницу без параметров
                window.location.href = window.location.pathname;
            }
        });
    }
}

/**
 * Инициализация графиков
 */
function initCharts() {
    // Инициализация будет выполнена на соответствующих страницах
    console.log('Инициализация графиков...');
}

// Дополнительные функции для аналитики (будут использоваться на других страницах)

/**
 * Рендеринг графика роста кампаний
 */
function renderCampaignGrowthChart(data) {
    const ctx = document.getElementById('campaignGrowthChart');
    if (!ctx) return;
    
    new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: data.map(item => item.date),
            datasets: [{
                label: 'Новые кампании',
                data: data.map(item => item.count),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Рост количества кампаний по дням'
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
 * Рендеринг графика уникальности креативов
 */
function renderCreativeUniquenessChart(data) {
    const ctx = document.getElementById('creativeUniquenessChart');
    if (!ctx) return;
    
    new Chart(ctx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Уникальные', 'Повторяющиеся'],
            datasets: [{
                data: [data.unique, data.duplicates],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Уникальность креативов'
                }
            }
        }
    });
}

// Экспорт функций для использования в других скриптах
window.uploadFile = uploadFile;
window.updateFileInfo = updateFileInfo;
window.showAlert = showAlert;
window.renderCampaignGrowthChart = renderCampaignGrowthChart;
window.renderCreativeUniquenessChart = renderCreativeUniquenessChart;

