class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.centerId,
    required this.centerName,
  });

  final int id;
  final String name;
  final String email;
  final String role;
  final int centerId;
  final String centerName;

  bool get isManager => role == 'center_manager';
  bool get isTeacher => role == 'teacher';

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      role: json['role'] as String? ?? 'teacher',
      centerId: json['center_id'] as int? ?? 0,
      centerName: json['center_name'] as String? ?? '',
    );
  }

  Map<String, dynamic> toJson() => <String, dynamic>{
    'id': id,
    'name': name,
    'email': email,
    'role': role,
    'center_id': centerId,
    'center_name': centerName,
  };
}

class Session {
  const Session({required this.token, required this.user});

  final String token;
  final AppUser user;

  factory Session.fromJson(Map<String, dynamic> json) {
    return Session(
      token: json['token'] as String? ?? '',
      user: AppUser.fromJson(json['user'] as Map<String, dynamic>),
    );
  }

  Map<String, dynamic> toJson() => <String, dynamic>{
    'token': token,
    'user': user.toJson(),
  };
}
