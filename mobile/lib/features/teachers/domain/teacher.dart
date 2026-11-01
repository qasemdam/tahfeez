class Teacher {
  const Teacher({
    required this.id,
    required this.name,
    required this.email,
    required this.isActive,
  });

  final int id;
  final String name;
  final String email;
  final bool isActive;

  factory Teacher.fromJson(Map<String, dynamic> json) {
    return Teacher(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      isActive: json['is_active'] as bool? ?? true,
    );
  }
}
