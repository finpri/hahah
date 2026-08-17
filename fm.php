<?php
// ============================================
// Простой PHP FileManager (без ограничений)
// ============================================

// Текущая директория
$current_dir = isset($_GET['dir']) ? $_GET['dir'] : '';
$current_path = __DIR__ . '/' . $current_dir;

// Если путь не существует или это файл - возвращаем в корень
if (!is_dir($current_path)) {
    $current_path = __DIR__;
    $current_dir = '';
}

// Обработка действий
$message = '';
$error = '';

// Создание папки
if (isset($_POST['create_dir'])) {
    $dir_name = trim($_POST['dir_name']);
    if (!empty($dir_name)) {
        $new_dir = $current_path . '/' . $dir_name;
        if (!file_exists($new_dir)) {
            mkdir($new_dir);
            $message = "Папка создана";
        } else {
            $error = "Папка уже существует";
        }
    }
}

// Загрузка файла
if (isset($_FILES['file'])) {
    $file = $_FILES['file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $dest = $current_path . '/' . basename($file['name']);
        move_uploaded_file($file['tmp_name'], $dest);
        $message = "Файл загружен";
    }
}

// Удаление
if (isset($_GET['delete'])) {
    $delete_path = $current_path . '/' . basename($_GET['delete']);
    if (is_file($delete_path)) {
        unlink($delete_path);
        $message = "Файл удален";
    } elseif (is_dir($delete_path)) {
        // Рекурсивное удаление
        function deleteDir($dir) {
            foreach (scandir($dir) as $item) {
                if ($item == '.' || $item == '..') continue;
                $path = $dir . '/' . $item;
                is_dir($path) ? deleteDir($path) : unlink($path);
            }
            rmdir($dir);
        }
        deleteDir($delete_path);
        $message = "Папка удалена";
    }
}

// Переименование
if (isset($_POST['rename'])) {
    $old = $current_path . '/' . $_POST['old_name'];
    $new = $current_path . '/' . trim($_POST['new_name']);
    if (file_exists($old) && !file_exists($new) && !empty($_POST['new_name'])) {
        rename($old, $new);
        $message = "Переименовано";
    }
}

// Список файлов
$items = scandir($current_path);
$items = array_diff($items, ['.', '..']);

// Сортировка (папки сверху)
usort($items, function($a, $b) use ($current_path) {
    $a_is_dir = is_dir($current_path . '/' . $a);
    $b_is_dir = is_dir($current_path . '/' . $b);
    if ($a_is_dir && !$b_is_dir) return -1;
    if (!$a_is_dir && $b_is_dir) return 1;
    return strcasecmp($a, $b);
});

// Родительская папка
$parent = dirname($current_dir);
if ($parent == '.') $parent = '';

// Форматирование размера
function formatSize($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' ГБ';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' МБ';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' КБ';
    return $bytes . ' Б';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FileManager</title>
    <style>
        body { font-family: Arial; max-width: 1000px; margin: 20px auto; padding: 0 15px; background: #f0f0f0; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; margin-top: 0; }
        .nav { padding: 10px; background: #f9f9f9; border-radius: 4px; margin: 10px 0; word-break: break-all; }
        .nav a { color: #4CAF50; text-decoration: none; font-weight: bold; }
        .nav a:hover { text-decoration: underline; }
        .msg { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .tools { display: flex; flex-wrap: wrap; gap: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px; margin: 15px 0; }
        .tools form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; flex: 1; }
        .tools input[type="text"], .tools input[type="file"] { padding: 8px; border: 1px solid #ddd; border-radius: 4px; flex: 1; min-width: 120px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; color: white; }
        .btn-green { background: #4CAF50; }
        .btn-green:hover { background: #45a049; }
        .btn-red { background: #f44336; }
        .btn-red:hover { background: #da190b; }
        .btn-orange { background: #ff9800; }
        .btn-orange:hover { background: #e68900; }
        .btn-blue { background: #2196F3; }
        .btn-blue:hover { background: #0b7dda; }
        .item { display: flex; justify-content: space-between; align-items: center; padding: 10px; margin: 5px 0; background: #f9f9f9; border-radius: 4px; flex-wrap: wrap; }
        .item:hover { background: #f0f0f0; }
        .item-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 150px; }
        .item-icon { font-size: 24px; }
        .item-name { word-break: break-all; }
        .item-name a { color: #333; text-decoration: none; }
        .item-name a:hover { text-decoration: underline; }
        .item-size { color: #666; font-size: 13px; margin-left: 10px; }
        .item-actions { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; }
        .rename-form { display: flex; gap: 5px; align-items: center; }
        .rename-form input[type="text"] { padding: 4px 8px; border: 1px solid #ddd; border-radius: 4px; width: 120px; }
        .empty { text-align: center; color: #999; padding: 40px 0; }
        @media (max-width: 600px) {
            .item { flex-direction: column; align-items: stretch; }
            .item-left { margin-bottom: 8px; }
            .rename-form input[type="text"] { width: 100%; }
            .tools form { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>📁 Файловый менеджер</h1>
    
    <!-- Навигация -->
    <div class="nav">
        <strong>Путь:</strong> /<?php echo htmlspecialchars($current_dir ?: ''); ?>
        <?php if ($current_dir): ?>
            <a href="?dir=<?php echo urlencode($parent); ?>"> ⬆ Наверх</a>
        <?php endif; ?>
    </div>
    
    <!-- Сообщения -->
    <?php if ($message): ?>
        <div class="msg success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="msg error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Инструменты -->
    <div class="tools">
        <form method="POST">
            <input type="text" name="dir_name" placeholder="Название папки" required>
            <input type="submit" name="create_dir" value="📁 Создать папку" class="btn btn-green">
        </form>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <input type="submit" value="⬆ Загрузить" class="btn btn-blue">
        </form>
    </div>
    
    <!-- Список -->
    <?php if (empty($items)): ?>
        <div class="empty">Папка пуста</div>
    <?php else: ?>
        <?php foreach ($items as $item): 
            $path = $current_path . '/' . $item;
            $is_dir = is_dir($path);
            $icon = $is_dir ? '📁' : '📄';
            $size = $is_dir ? '' : formatSize(filesize($path));
            $link = $is_dir ? '?dir=' . urlencode($current_dir ? $current_dir . '/' . $item : $item) : $current_dir . '/' . $item;
        ?>
            <div class="item">
                <div class="item-left">
                    <span class="item-icon"><?php echo $icon; ?></span>
                    <span class="item-name">
                        <a href="<?php echo htmlspecialchars($link); ?>">
                            <?php echo htmlspecialchars($item); ?>
                        </a>
                    </span>
                    <?php if ($size): ?>
                        <span class="item-size"><?php echo $size; ?></span>
                    <?php endif; ?>
                </div>
                <div class="item-actions">
                    <form method="POST" class="rename-form">
                        <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($item); ?>">
                        <input type="text" name="new_name" value="<?php echo htmlspecialchars($item); ?>" required>
                        <button type="submit" name="rename" class="btn btn-orange" style="padding:4px 12px;">✏️</button>
                    </form>
                    <a href="?dir=<?php echo urlencode($current_dir); ?>&delete=<?php echo urlencode($item); ?>" 
                       class="btn btn-red" 
                       onclick="return confirm('Удалить?')">🗑</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
