import os
import sys

def main():
    # Используем текущую папку или переданный путь
    root_dir = os.getcwd() if len(sys.argv) < 2 else sys.argv[1]

    # Папки для поиска
    subdirs = ['Pages', 'Save']
    output_file = os.path.join(root_dir, 'index.txt')

    pages = set()  # множество, чтобы избежать дубликатов

    for sub in subdirs:
        target_dir = os.path.join(root_dir, sub)
        if not os.path.isdir(target_dir):
            print(f"Предупреждение: папка {target_dir} не найдена, пропускаем.")
            continue

        # Рекурсивный обход (включает вложенные папки)
        for dirpath, _, filenames in os.walk(target_dir):
            for filename in filenames:
                if filename.endswith('.iopnwiki'):
                    name_without_ext = os.path.splitext(filename)[0]
                    pages.add(name_without_ext)

    # Запись результата
    if pages:
        with open(output_file, 'w', encoding='utf-8') as f:
            for name in sorted(pages):
                f.write(name + '\n')
        print(f"Готово. Записано {len(pages)} имён в {output_file}")
    else:
        print("Файлы .iopnwiki не найдены в папках Pages и Save.")

if __name__ == '__main__':
    main()