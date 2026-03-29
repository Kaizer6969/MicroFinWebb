import 'dart:io';

void main() {
  final dir = Directory('microfin_mobile/lib');
  final files = dir.listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'));
  
  final regex = RegExp(r'const\s+TextStyle\s*\([^)]*AppColors\.(?:text|bg|card|surface|separator)[a-zA-Z]*[^)]*\)');
  int count = 0;
  
  for (final file in files) {
    String content = file.readAsStringSync();
    if (regex.hasMatch(content)) {
      content = content.replaceAllMapped(regex, (match) {
        return match.group(0)!.replaceFirst('const ', '');
      });
      file.writeAsStringSync(content);
      count++;
    }
  }
  print('Modified $count files');
}
