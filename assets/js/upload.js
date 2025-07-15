// Упрощенный JavaScript для загрузки файлов

document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    const fileInput = document.getElementById('csvFile');
    const fileLabel = document.querySelector('.file-label');
    const labelText = document.querySelector('.label-text');
    const fileName = document.querySelector('.file-name');
    const uploadBtn = document.getElementById('uploadBtn');
    const uploadStatus = document.getElementById('uploadStatus');

    // Обработчик выбора файла
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        
        if (file) {
            // Проверка типа файла
            if (!file.name.toLowerCase().endsWith('.csv')) {
                showAlert('Пожалуйста, выберите CSV файл', 'danger');
                this.value = '';
                resetFileDisplay();
                return;
            }

            // Проверка размера файла (50MB)
            const maxSize = 50 * 1024 * 1024;
            if (file.size > maxSize) {
                showAlert('Файл слишком большой. Максимальный размер: 50MB', 'danger');
                this.value = '';
                resetFileDisplay();
                return;
            }

            // Обновляем отображение
            updateFileDisplay(file);
        } else {
            resetFileDisplay();
        }
    });

    // Обработчик отправки формы
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const file = fileInput.files[0];
        
        if (!file) {
            showAlert('Выберите файл для загрузки', 'warning');
            return;
        }

        uploadFile(file);
    });

    // Функция обновления отображения файла
    function updateFileDisplay(file) {
        fileLabel.classList.add('file-selected');
        labelText.textContent = 'Файл выбран:';
        fileName.textContent = `${file.name} (${formatFileSize(file.size)})`;
        uploadBtn.disabled = false;
    }

    // Функция сброса отображения файла
    function resetFileDisplay() {
        fileLabel.classList.remove('file-selected');
        labelText.textContent = 'Выбрать CSV файл';
        fileName.textContent = '';
        uploadBtn.disabled = true;
    }

    // Функция загрузки файла
    function uploadFile(file) {
        const formData = new FormData();
        formData.append('csv_file', file);

        // Показываем статус загрузки
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Загрузка...';
        uploadStatus.style.display = 'block';

        fetch('../api/upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Файл успешно загружен и обработан!', 'success');
                
                // Сбрасываем форму
                uploadForm.reset();
                resetFileDisplay();
                
                // Перенаправляем на страницу аналитики через 2 секунды
                setTimeout(() => {
                    window.location.href = 'analytics.php';
                }, 2000);
            } else {
                showAlert('Ошибка: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Ошибка:', error);
            showAlert('Произошла ошибка при загрузке файла', 'danger');
        })
        .finally(() => {
            // Восстанавливаем кнопку и скрываем статус
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Загрузить файл';
            uploadStatus.style.display = 'none';
        });
    }

    // Функция показа уведомлений
    function showAlert(message, type) {
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

        // Вставляем в контейнер
        const alertContainer = document.getElementById('alertContainer');
        alertContainer.appendChild(alertDiv);

        // Автоматически скрываем через 5 секунд
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Функция форматирования размера файла
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});
