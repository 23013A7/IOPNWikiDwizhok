import os
import sys

def main():
    root_dir = os.getcwd() if len(sys.argv) < 2 else sys.argv[1]

    subdirs = ['Pages', 'Save'] # папки для индексации
    output_file = os.path.join(root_dir, 'index.txt')

    pages = set()

    for sub in subdirs:
        target_dir = os.path.join(root_dir, sub)
        if not os.path.isdir(target_dir):
            print(f"Ошибка: папка {target_dir} несуществутеэ")
            continue

        #тут рекурсия обход и тд в внутрии папок 
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
