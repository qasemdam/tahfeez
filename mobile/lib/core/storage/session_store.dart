import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../features/auth/domain/session.dart';

/// Local session persistence.
///
/// MVP placeholder: SharedPreferences is not secure storage. Replaced by
/// flutter_secure_storage in the offline/sync slice — see docs/offline-sync.md.
class SessionStore {
  static const String _key = 'tahfeez.session';

  Future<Session?> read() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    final String? raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) {
      return null;
    }
    try {
      return Session.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } on FormatException {
      await prefs.remove(_key);
      return null;
    }
  }

  Future<void> save(Session session) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, jsonEncode(session.toJson()));
  }

  Future<void> clear() async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }
}
