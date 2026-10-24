/// Datos públicos del servidor (GET /api/v1/meta)
class Meta {
  Meta({required this.minVersion, required this.webUrl, required this.catalogs, required this.realtime});

  final String minVersion;
  final String webUrl;
  final Map<String, dynamic> catalogs;
  final Map<String, dynamic> realtime;

  factory Meta.fromJson(Map<String, dynamic> json) => Meta(
        minVersion: json['min_desktop_version']?.toString() ?? '0.0.0',
        webUrl: json['web_url']?.toString() ?? '',
        catalogs: Map<String, dynamic>.from(json['catalogs'] as Map? ?? const {}),
        realtime: Map<String, dynamic>.from(json['realtime'] as Map? ?? const {}),
      );
}

class Company {
  Company({required this.id, required this.name, required this.isDefault});

  final int id;
  final String name;
  final bool isDefault;

  factory Company.fromJson(Map<String, dynamic> json) =>
      Company(id: json['id'] as int, name: json['name'].toString(), isDefault: json['is_default'] == true);
}

/// Usuario con sesión (GET /api/v1/me): sin datos personales sensibles
class Me {
  Me({
    required this.id,
    required this.name,
    required this.email,
    required this.roles,
    required this.permissions,
    required this.home,
    required this.companies,
    required this.currentCompanyId,
    required this.premium,
    required this.privacyRequired,
    required this.twoFactorSetupRequired,
  });

  final int id;
  final String name;
  final String email;
  final List<String> roles;
  final Set<String> permissions;

  /// Pantalla inicial: dispatch | diets | dispatches | web
  final String home;
  final List<Company> companies;
  final int? currentCompanyId;
  final Set<String> premium;
  final bool privacyRequired;
  final bool twoFactorSetupRequired;

  bool can(String permission) => permissions.contains(permission);
  bool hasPremium(String feature) => premium.contains(feature);

  /// Falta algo que se resuelve en la web (política o 2FA): la operación responde 403
  bool get needsWebAction => privacyRequired || twoFactorSetupRequired;

  Company? get currentCompany =>
      companies.where((c) => c.id == currentCompanyId).firstOrNull ?? companies.firstOrNull;

  factory Me.fromJson(Map<String, dynamic> json) {
    final compliance = Map<String, dynamic>.from(json['compliance'] as Map? ?? const {});
    List<String> strings(String key) => (json[key] as List? ?? const []).map((e) => '$e').toList();

    return Me(
      id: json['id'] as int,
      name: json['name'].toString(),
      email: json['email'].toString(),
      roles: strings('roles'),
      permissions: strings('permissions').toSet(),
      home: json['home']?.toString() ?? 'web',
      companies: (json['companies'] as List? ?? const [])
          .map((c) => Company.fromJson(Map<String, dynamic>.from(c as Map)))
          .toList(),
      currentCompanyId: json['current_company_id'] as int?,
      premium: strings('premium').toSet(),
      privacyRequired: compliance['privacy_required'] == true,
      twoFactorSetupRequired: compliance['two_factor_setup_required'] == true,
    );
  }
}
