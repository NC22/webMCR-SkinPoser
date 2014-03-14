webMCR Add-on SkinPoser v1.2

Установка:

1. Скопировать файлы из папки 'upload' в папку с webMCR
1.1 [для UNIX систем] Выдать всем файлам и каталогам webMCR из подпапки data/sp2/ права на чтение \ запись
1.2 [для версии 2.4b] Переместить файлы data/style/Default в папку style/Default (или в папку шаблона по умолчанию)
2. Перейти по ссылке your_site/?mode=skinposer , где 'your_site' - адрес вашего сайта

Обновление на новую версию

Начиная с версии SP 1.2, папка instruments/sp2/ (подкаталоги skins и upload) перемещена в data/sp2/
Удалите опцию 'sp_skins' из $bd_names в файле config.php, учтите пункт 1.2 раздела "Установка"