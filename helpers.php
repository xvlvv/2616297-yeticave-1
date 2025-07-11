<?php

require_once 'constants.php';

/**
 * Проверяет переданную дату на соответствие формату 'ГГГГ-ММ-ДД'
 *
 * Примеры использования:
 * is_date_valid('2019-01-01'); // true
 * is_date_valid('2016-02-29'); // true
 * is_date_valid('2019-04-31'); // false
 * is_date_valid('10.10.2010'); // false
 * is_date_valid('10/10/2010'); // false
 *
 * @param string $date Дата в виде строки
 *
 * @return bool true при совпадении с форматом 'ГГГГ-ММ-ДД', иначе false
 */
function is_date_valid(string $date): bool
{
    $format_to_check = 'Y-m-d';
    $dateTimeObj = date_create_from_format($format_to_check, $date);

    return $dateTimeObj !== false && array_sum(date_get_last_errors()) === 0;
}

/**
 * Создает подготовленное выражение на основе готового SQL запроса и переданных данных
 *
 * @param $link mysqli Ресурс соединения
 * @param $sql string SQL запрос с плейсхолдерами вместо значений
 * @param array $data Данные для вставки на место плейсхолдеров
 *
 * @return mysqli_stmt Подготовленное выражение
 */
function db_get_prepare_stmt($link, $sql, $data = [])
{
    $stmt = mysqli_prepare($link, $sql);

    if ($stmt === false) {
        $errorMsg = 'Не удалось инициализировать подготовленное выражение: ' . mysqli_error($link);
        die($errorMsg);
    }

    if ($data) {
        $types = '';
        $stmt_data = [];

        foreach ($data as $value) {
            $type = 's';

            $type = match (true) {
                is_int($value) => 'i',
                is_string($value) => 's',
                is_double($value) => 'd'
            };

            if ($type) {
                $types .= $type;
                $stmt_data[] = $value;
            }
        }

        $values = array_merge([$stmt, $types], $stmt_data);

        $func = 'mysqli_stmt_bind_param';
        $func(...$values);

        if (mysqli_errno($link) > 0) {
            $errorMsg = 'Не удалось связать подготовленное выражение с параметрами: ' . mysqli_error($link);
            die($errorMsg);
        }
    }

    return $stmt;
}

/**
 * Возвращает корректную форму множественного числа
 * Ограничения: только для целых чисел
 *
 * Пример использования:
 * $remaining_minutes = 5;
 * echo "Я поставил таймер на {$remaining_minutes} " .
 *     get_noun_plural_form(
 *         $remaining_minutes,
 *         'минута',
 *         'минуты',
 *         'минут'
 *     );
 * Результат: "Я поставил таймер на 5 минут"
 *
 * @param int $number Число, по которому вычисляем форму множественного числа
 * @param string $one Форма единственного числа: яблоко, час, минута
 * @param string $two Форма множественного числа для 2, 3, 4: яблока, часа, минуты
 * @param string $many Форма множественного числа для остальных чисел
 *
 * @return string Рассчитанная форма множественнго числа
 */
function get_noun_plural_form(int $number, string $one, string $two, string $many): string
{
    $mod10 = $number % 10;
    $mod100 = $number % 100;

    switch (true) {
        case ($mod100 >= 11 && $mod100 <= 20):
            return $many;

        case ($mod10 > 5):
            return $many;

        case ($mod10 === 1):
            return $one;

        case ($mod10 >= 2 && $mod10 <= 4):
            return $two;

        default:
            return $many;
    }
}

/**
 * Подключает шаблон, передает туда данные и возвращает итоговый HTML контент
 * @param string $name Путь к файлу шаблона относительно папки templates
 * @param array $data Ассоциативный массив с данными для шаблона
 * @return string Итоговый HTML
 */
function include_template($name, array $data = [])
{
    $name = 'templates/' . $name;
    $result = '';

    if (!is_readable($name)) {
        return $result;
    }

    ob_start();
    extract($data);
    require $name;

    $result = ob_get_clean();

    return $result;
}

/**
 * Форматирует цену добавляя разделители для тысяч и символ рубля
 *
 * @param int $price Не отформатированная цена
 * @return string Отформатированная цена с символом рубля
 */
function format_price(int $price, string $curr_symbol = '₽'): string
{
    $formatted_price = number_format($price, 0, '', ' ');
    return "$formatted_price $curr_symbol";
}

/**
 * Возвращает количество часов и минут с текущего времени до переданной даты
 * в формате ассоциативного массива. В случае если дата конца временного промежутка
 * уже прошла, возвращает массив нулевых значений
 *
 * @param string $string_date Дата конца временного промежутка в формате строки
 * @return array<string, int> Ассоциативный массив, количество часов и минут временного интервала
 */
function get_dt_range(string $string_date): array
{
    $current_time = date_create('now');
    $end_date = date_create($string_date);

    if ($end_date <= $current_time) {
        return [
            'hours' => 0,
            'minutes' => 0,
            'seconds' => 0,
        ];
    }

    $range = date_diff($current_time, $end_date);

    $total_hours = ($range->d * 24) + $range->h;
    $total_minutes = $range->i;
    $total_seconds = $range->s;

    return [
        'hours' => $total_hours,
        'minutes' => $total_minutes,
        'seconds' => $total_seconds,
    ];
}

/**
 * Возвращает количество прошедших часов и минут с переданной даты до текущего времени
 * в формате ассоциативного массива. Если дата еще не наступила, возвращает нулевые значения.
 *
 * @param string $string_date Начальная дата временного промежутка в формате строки
 * @return array<string, int> Ассоциативный массив, количество часов и минут временного интервала
 */
function get_passed_time(string $string_date): array
{
    $current_time = date_create('now');
    $start_date = date_create($string_date);

    if ($start_date >= $current_time) {
        return [
            'hours' => 0,
            'minutes' => 0,
        ];
    }

    $range = date_diff($start_date, $current_time);

    $total_hours = ($range->d * 24) + $range->h;
    $total_minutes = $range->i;

    return [
        'hours' => $total_hours,
        'minutes' => $total_minutes,
    ];
}

/**
 * Функция форматирования массива временного интервала в строку
 *
 * @param array<string, int> $dt_range Ассоциативный массив, количество часов и минут временного интервала
 * @return string Строка в формате ЧЧ:ММ
 */
function format_dt_range(array $dt_range): string
{
    $formatted_hours = sprintf(
        "%02d",
        $dt_range['hours'] ?? 0,
    );
    $formatted_minutes = sprintf(
        "%02d",
        $dt_range['minutes'] ?? 0,
    );

    return "$formatted_hours:$formatted_minutes";
}

/**
 * Завершает работу программы с сообщением об ошибке
 *
 * @param string $message Сообщение об ошибке
 * @param int $code Код ошибки сервера
 * @return void
 */
function exit_with_message(string $message, int $code = 500): void
{
    http_response_code($code);
    die($message);
}

/**
 * Выполняет SQL запрос
 *
 * @param mysqli $conn Ресурс подключения в БД
 * @param string $sql Текст подготовленного запроса
 * @param array $data Выходные переменные для привязки к запросу
 * @return mysqli_result|bool Данные в формате ассоциативного массива, заканчивает выполнение PHP-сценария в случае ошибки
 */
function execute_query(mysqli $conn, string $sql, array $data = []): mysqli_result|bool
{
    $stmt = db_get_prepare_stmt(
        $conn,
        $sql,
        $data,
    );

    $stmt_result = $stmt->execute();

    if ($stmt_result === false) {
        exit_with_message('Ошибка в обработке запроса. Пожалуйста, попробуйте позже.');
    }

    return $stmt->get_result();
}

/**
 * Производит валидацию данных, используя функции-валидаторы
 *
 * @param array $data Массив данных для валидации
 * @param array<string, array{callable|string} $rules Правила валидации
 *        в формате ['field_name' => [rule1, rule2, ...]]
 *        где каждый rule может быть:
 *        - callable: функция-валидатор (возвращает string|false)
 * @return array<string, string[] Массив ошибок в формате
 *        ['field_name' => ['ошибка1, 'ошибка2', ...]]
 */
function validate(array $data, array $rules): array
{
    $messages = [];

    foreach ($rules as $field => $rule) {
        foreach ($rule as $callback) {
            if (
                is_string($callback)
                && !function_exists($callback)
            ) {
                continue;
            }

            $validation_result = call_user_func($callback, $data[$field] ?? null);

            if ($validation_result !== false) {
                $messages[$field][] = $validation_result;
            }
        }
    }

    return $messages;
}

/**
 * Конвертирует mime типы в расширения файлов
 *
 * @param array $mime_types Массив mime типов для конвертации
 * @return array Массив сконвертированных mime типов в соответствующие им расширения файлов
 */
function mime_to_ext(array $mime_types): array
{
    foreach ($mime_types as &$mime) {
        $mime = MIME_MAP[$mime] ?? $mime;
    }

    return $mime_types;
}

/**
 * Форматирует массив ошибок в строку
 *
 * @param array $error_field Массив сообщений ошибок
 * @return string Список сообщений ошибок через запятую в виде строки
 */
function format_validation_errors(array $error_field): string
{
    if (empty($error_field)) {
        return '';
    }

    return implode(', ', $error_field);
}

/**
 * Загружает файл в директорию сайта
 *
 * @param string $file_name Имя загруженного файла
 * @param string $file_path Путь к временной директории
 * @param string $file_prefix Префикс для нового имени
 * @return array<string, string|bool> Ассоциативный массив с результатом загрузки файла
 */
function upload_file(
    string $file_name,
    string $file_path,
    string $file_prefix = ''
): array {
    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
    $new_file_path = 'uploads/' . uniqid($file_prefix) . '.' . $file_extension;

    if (move_uploaded_file($file_path, $new_file_path) === true) {
        return [
            'status' => true,
            'file_path' => $new_file_path,
        ];
    }

    return [
        'status' => false,
    ];
}

/**
 * Возвращает id текущего пользователя
 *
 * @return int|null Id пользователя если существует, либо null
 */
function get_user_id(): ?int
{
    return (int)($_SESSION['user_data']['user_id'] ?? null);
}

/**
 * Возвращает имя пользователя
 *
 * @return string|null Имя пользователя либо null если пользователь не авторизирован
 */
function get_user_name(): ?string
{
    return $_SESSION['user_data']['name'] ?? null;
}

/**
 * Возвращает ссылки пагинации
 *
 * @param int $limit Число элементов на странице
 * @param int $page_number Номер текущей страницы
 * @param int $total Общее количество элементов
 * @return array Ассоциативный массив, где pages - массив ссылок пагинации, prev и next - ссылки для кнопок
 *               Назад и Вперед соответственно
 */
function calculate_pager_state(
    int $limit,
    int $page_number,
    int $total
): array {
    $total_pages = (int)ceil($total / $limit);

    $result = [
        'pages' => [],
        'prev' => null,
        'next' => null,
    ];

    if ($total_pages <= 1) {
        return $result;
    }

    if ($page_number > 1) {
        $result['prev'] = $page_number - 1;
    }

    if ($page_number + 1 <= $total_pages) {
        $result['next'] = $page_number + 1;
    }

    for ($page = 1; $page <= $total_pages; $page++) {
        $result['pages'][$page] = [
            'current' => $page === $page_number,
        ];
    }

    return $result;
}

/**
 * Возвращает смещение поиска записей для текущей страницы
 *
 * @param int $limit Число элементов на странице
 * @param int $page_number Номер текущей страницы
 * @return int Смещение поиска записей для текущей страницы
 */
function get_current_page_offset(
    int $limit,
    int $page_number,
): int {
    if ($page_number < 1) {
        return 0;
    }

    return ($page_number - 1) * $limit;
}

/**
 * Меняет текущую строку запроса, добавляя/изменяя GET параметры
 *
 * @param array $params Список параметров которые необходимо добавить в строку
 * @return string Новая измененная строка
 */
function change_get_parameter(array $params): string
{
    return '?' . http_build_query(
            array_merge($_GET, $params),
            '',
            '&amp;'
        );
}

/**
 * Возвращает количество времени, прошедшего с определенной даты в текстовом представлении
 *
 * @param string $date Дата конца временного промежутка в формате строки
 * @return string Текстовое представление временного промежутка
 */
function to_time_ago_format(string $date): string
{
    $dt_range = get_passed_time($date);
    $date_to_format = date_create($date);
    if ($date_to_format === false) {
        return '';
    }
    $yesterday_date = date_create('yesterday');
    $date_time = date_format($date_to_format, 'H:i');
    $was_yesterday = $date_to_format->format('Y-m-d') === $yesterday_date->format('Y-m-d');

    return match (true) {
        $dt_range['hours'] === 0 => $dt_range['minutes'] . ' ' . get_noun_plural_form(
                $dt_range['minutes'],
                'минуту',
                'минуты',
                'минут'
            ) . ' назад',
        $dt_range['hours'] === 1 => 'Час назад',
        $dt_range['hours'] < 24 && !$was_yesterday => $dt_range['hours'] . ' ' . get_noun_plural_form(
                $dt_range['hours'],
                'час',
                'часа',
                'часов'
            ) . ' назад',
        $was_yesterday => "Вчера, в $date_time",
        default => date_format($date_to_format, 'd.m.y в H:i'),
    };
}