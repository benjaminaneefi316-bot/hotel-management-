import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_exception.dart';
import '../api/hotel_repository.dart';
import '../models/user_account.dart';
import '../theme/app_theme.dart';
import '../widgets/app_widgets.dart';

/// Admin-only staff and guest account management.
class UsersScreen extends StatefulWidget {
  const UsersScreen({super.key});

  @override
  State<UsersScreen> createState() => _UsersScreenState();
}

class _UsersScreenState extends State<UsersScreen> {
  final TextEditingController _search = TextEditingController();
  late Future<List<UserAccount>> _future;
  String _status = '';
  String _role = '';
  String? _error;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<List<UserAccount>> _load() => context.read<HotelRepository>().users(
        search: _search.text.trim(),
        status: _status,
        role: _role,
      );

  void _reload() => setState(() {
        _error = null;
        _future = _load();
      });

  Future<void> _openForm([UserAccount? user]) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => UserFormScreen(user: user),
      ),
    );
    if (saved == true && mounted) {
      _reload();
    }
  }

  Future<void> _setActive(UserAccount user, bool active) async {
    final verb = active ? 'Activate' : 'Deactivate';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('$verb ${user.name}?'),
        content: Text(
          active
              ? 'They will be able to sign in again.'
              : 'They will be signed out everywhere and cannot sign in until '
                  'reactivated.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(verb),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) {
      return;
    }

    try {
      final repo = context.read<HotelRepository>();
      if (active) {
        await repo.activateUser(user.id);
      } else {
        await repo.deactivateUser(user.id);
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${user.name} ${active ? 'activated' : 'deactivated'}.')),
        );
        _reload();
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _error = e.message);
      }
    }
  }

  Future<void> _resetPassword(UserAccount user) async {
    final changed = await showDialog<bool>(
      context: context,
      builder: (_) => _ResetPasswordDialog(user: user),
    );
    if (changed == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Password reset for ${user.name}.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Users'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _reload,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openForm(),
        icon: const Icon(Icons.person_add_alt_1),
        label: const Text('New user'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: AppTextField(
              controller: _search,
              label: 'Search name, username or email',
              prefixIcon: Icons.search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _reload(),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _FilterChip(
                    label: 'Everyone',
                    selected: _status.isEmpty && _role.isEmpty,
                    onTap: () {
                      setState(() {
                        _status = '';
                        _role = '';
                      });
                      _reload();
                    },
                  ),
                  const SizedBox(width: 8),
                  _FilterChip(
                    label: 'Active',
                    selected: _status == 'active',
                    onTap: () {
                      setState(() => _status = 'active');
                      _reload();
                    },
                  ),
                  const SizedBox(width: 8),
                  _FilterChip(
                    label: 'Deactivated',
                    selected: _status == 'inactive',
                    onTap: () {
                      setState(() => _status = 'inactive');
                      _reload();
                    },
                  ),
                  const SizedBox(width: 8),
                  _FilterChip(
                    label: 'Admins',
                    selected: _role == 'admin',
                    onTap: () {
                      setState(() => _role = _role == 'admin' ? '' : 'admin');
                      _reload();
                    },
                  ),
                  const SizedBox(width: 8),
                  _FilterChip(
                    label: 'Reception',
                    selected: _role == 'receptionist',
                    onTap: () {
                      setState(() =>
                          _role = _role == 'receptionist' ? '' : 'receptionist');
                      _reload();
                    },
                  ),
                  const SizedBox(width: 8),
                  _FilterChip(
                    label: 'Guests',
                    selected: _role == 'guest',
                    onTap: () {
                      setState(() => _role = _role == 'guest' ? '' : 'guest');
                      _reload();
                    },
                  ),
                ],
              ),
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
              child: Text(
                _error!,
                style: const TextStyle(color: AppTheme.danger),
              ),
            ),
          Expanded(
            child: FutureBuilder<List<UserAccount>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError || !snapshot.hasData) {
                  return EmptyState(
                    icon: Icons.wifi_off,
                    title: 'Could not load users',
                    message: '${snapshot.error}',
                    onRetry: _reload,
                  );
                }

                final users = snapshot.data!;
                if (users.isEmpty) {
                  return const EmptyState(
                    icon: Icons.group_off_outlined,
                    title: 'No matching accounts',
                    message: 'Try a different search or filter.',
                  );
                }

                return RefreshIndicator(
                  onRefresh: () async => _reload(),
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
                    itemCount: users.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      final user = users[index];
                      return _UserCard(
                        user: user,
                        onEdit: () => _openForm(user),
                        onToggle: () => _setActive(user, !user.isActive),
                        onResetPassword: () => _resetPassword(user),
                      );
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _UserCard extends StatelessWidget {
  const _UserCard({
    required this.user,
    required this.onEdit,
    required this.onToggle,
    required this.onResetPassword,
  });

  final UserAccount user;
  final VoidCallback onEdit;
  final VoidCallback onToggle;
  final VoidCallback onResetPassword;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(AppTheme.radius),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppTheme.radius),
        onTap: onEdit,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: AppTheme.panel(),
          child: Row(
            children: [
              CircleAvatar(
                radius: 22,
                backgroundColor: AppTheme.charcoal,
                child: Text(
                  user.name.isEmpty ? '?' : user.name[0].toUpperCase(),
                  style: const TextStyle(
                    color: AppTheme.gold,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            user.name,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 15.5,
                              color: AppTheme.charcoal,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        StatusPill(
                          label: user.statusLabel,
                          color: user.isActive
                              ? AppTheme.success
                              : AppTheme.danger,
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '@${user.username} · ${user.email}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AppTheme.muted,
                        fontSize: 12.5,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        ...user.roleLabels.map(
                          (label) => Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: AppTheme.sand,
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(color: AppTheme.border),
                            ),
                            child: Text(
                              label,
                              style: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ),
                        if (user.twoFactorEnabled)
                          const Icon(
                            Icons.verified_user,
                            size: 15,
                            color: AppTheme.success,
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              PopupMenuButton<String>(
                onSelected: (value) {
                  switch (value) {
                    case 'edit':
                      onEdit();
                      break;
                    case 'toggle':
                      onToggle();
                      break;
                    case 'password':
                      onResetPassword();
                      break;
                  }
                },
                itemBuilder: (context) => [
                  const PopupMenuItem(value: 'edit', child: Text('Edit details')),
                  const PopupMenuItem(
                    value: 'password',
                    child: Text('Reset password'),
                  ),
                  PopupMenuItem(
                    value: 'toggle',
                    child: Text(user.isActive ? 'Deactivate' : 'Activate'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
    );
  }
}

/// Create a new account, or edit an existing one.
class UserFormScreen extends StatefulWidget {
  const UserFormScreen({super.key, this.user});

  final UserAccount? user;

  @override
  State<UserFormScreen> createState() => _UserFormScreenState();
}

class _UserFormScreenState extends State<UserFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _username;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  final TextEditingController _password = TextEditingController();
  final TextEditingController _confirm = TextEditingController();

  late Future<List<RoleOption>> _rolesFuture;
  final Set<String> _selectedRoles = {};
  String _status = 'active';
  bool _working = false;
  String? _error;

  bool get _editing => widget.user != null;

  @override
  void initState() {
    super.initState();
    final user = widget.user;
    _name = TextEditingController(text: user?.name ?? '');
    _username = TextEditingController(text: user?.username ?? '');
    _email = TextEditingController(text: user?.email ?? '');
    _phone = TextEditingController(text: user?.phone ?? '');
    _status = user?.status == 'inactive' ? 'inactive' : 'active';
    _selectedRoles.addAll(user?.roles ?? const []);
    _rolesFuture = context.read<HotelRepository>().roleOptions();
  }

  @override
  void dispose() {
    _name.dispose();
    _username.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }
    if (_selectedRoles.isEmpty) {
      setState(() => _error = 'Choose at least one role.');
      return;
    }
    if (!_editing && _password.text.isEmpty) {
      setState(() => _error = 'Enter a password for the new account.');
      return;
    }

    setState(() {
      _working = true;
      _error = null;
    });

    try {
      final repo = context.read<HotelRepository>();
      if (_editing) {
        await repo.updateUser(
          id: widget.user!.id,
          name: _name.text.trim(),
          username: _username.text.trim(),
          email: _email.text.trim(),
          phone: _phone.text.trim(),
          status: _status,
          roles: _selectedRoles.toList(),
        );
      } else {
        await repo.createUser(
          name: _name.text.trim(),
          username: _username.text.trim(),
          email: _email.text.trim(),
          phone: _phone.text.trim(),
          status: _status,
          password: _password.text,
          passwordConfirmation: _confirm.text,
          roles: _selectedRoles.toList(),
        );
      }

      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) {
        setState(() => _working = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_editing ? 'Edit user' : 'New user')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_error != null) ...[
              Text(_error!, style: const TextStyle(color: AppTheme.danger)),
              const SizedBox(height: 12),
            ],
            AppTextField(
              controller: _name,
              label: 'Full name',
              prefixIcon: Icons.person_outline,
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'Enter a name.' : null,
            ),
            const SizedBox(height: 12),
            AppTextField(
              controller: _username,
              label: 'Username',
              hint: 'letters, numbers, _ or -',
              prefixIcon: Icons.alternate_email,
              validator: (v) {
                final value = v?.trim() ?? '';
                if (value.isEmpty) {
                  return 'Enter a username.';
                }
                if (!RegExp(r'^[A-Za-z0-9_-]+$').hasMatch(value)) {
                  return 'Use only letters, numbers, _ or -.';
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            AppTextField(
              controller: _email,
              label: 'Email',
              keyboardType: TextInputType.emailAddress,
              prefixIcon: Icons.mail_outline,
              validator: (v) {
                final value = v?.trim() ?? '';
                if (!value.contains('@') || !value.contains('.')) {
                  return 'Enter a valid email address.';
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            AppTextField(
              controller: _phone,
              label: 'Phone (optional)',
              keyboardType: TextInputType.phone,
              prefixIcon: Icons.phone_outlined,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _status,
              decoration: const InputDecoration(labelText: 'Status'),
              items: const [
                DropdownMenuItem(value: 'active', child: Text('Active')),
                DropdownMenuItem(value: 'inactive', child: Text('Deactivated')),
              ],
              onChanged: (value) => setState(() => _status = value ?? 'active'),
            ),
            if (!_editing) ...[
              const SizedBox(height: 12),
              AppTextField(
                controller: _password,
                label: 'Password',
                hint: 'at least 12 characters, mixed case, number, symbol',
                obscureText: true,
                prefixIcon: Icons.lock_outline,
                validator: (v) {
                  if (v == null || v.isEmpty) {
                    return 'Enter a password.';
                  }
                  if (v.length < 12) {
                    return 'Use at least 12 characters.';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 12),
              AppTextField(
                controller: _confirm,
                label: 'Confirm password',
                obscureText: true,
                prefixIcon: Icons.lock_outline,
                validator: (v) =>
                    v != _password.text ? 'Passwords do not match.' : null,
              ),
            ],
            const SizedBox(height: 20),
            const Text('ROLES', style: AppTheme.eyebrow),
            const SizedBox(height: 8),
            FutureBuilder<List<RoleOption>>(
              future: _rolesFuture,
              builder: (context, snapshot) {
                if (!snapshot.hasData) {
                  return const Padding(
                    padding: EdgeInsets.all(8),
                    child: LinearProgressIndicator(),
                  );
                }
                return Column(
                  children: snapshot.data!
                      .map(
                        (role) => CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          value: _selectedRoles.contains(role.name),
                          onChanged: (checked) => setState(() {
                            if (checked == true) {
                              _selectedRoles.add(role.name);
                            } else {
                              _selectedRoles.remove(role.name);
                            }
                          }),
                          title: Text(role.label),
                          subtitle: Text(
                            role.description,
                            style: const TextStyle(fontSize: 12),
                          ),
                          controlAffinity: ListTileControlAffinity.leading,
                        ),
                      )
                      .toList(growable: false),
                );
              },
            ),
            const SizedBox(height: 18),
            SizedBox(
              height: 52,
              child: FilledButton(
                onPressed: _working ? null : _save,
                child: Text(
                  _working
                      ? 'Saving…'
                      : (_editing ? 'Save changes' : 'Create account'),
                ),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              _editing
                  ? 'Changing the status to Deactivated signs the user out '
                      'everywhere immediately.'
                  : 'New accounts start without two-factor. The user can connect '
                      'an authenticator from Profile → Security.',
              style: const TextStyle(color: AppTheme.muted, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}

class _ResetPasswordDialog extends StatefulWidget {
  const _ResetPasswordDialog({required this.user});

  final UserAccount user;

  @override
  State<_ResetPasswordDialog> createState() => _ResetPasswordDialogState();
}

class _ResetPasswordDialogState extends State<_ResetPasswordDialog> {
  final TextEditingController _password = TextEditingController();
  final TextEditingController _confirm = TextEditingController();
  bool _working = false;
  String? _error;

  @override
  void dispose() {
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_password.text.length < 12) {
      setState(() => _error = 'Use at least 12 characters.');
      return;
    }
    if (_password.text != _confirm.text) {
      setState(() => _error = 'Passwords do not match.');
      return;
    }

    setState(() {
      _working = true;
      _error = null;
    });

    try {
      await context.read<HotelRepository>().resetUserPassword(
            id: widget.user.id,
            password: _password.text,
            confirmation: _confirm.text,
          );
      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) {
        setState(() => _working = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text('Reset password · ${widget.user.name}'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'They will be signed out everywhere and must use the new password.',
              style: TextStyle(fontSize: 13, color: AppTheme.muted),
            ),
            const SizedBox(height: 12),
            if (_error != null) ...[
              Text(_error!, style: const TextStyle(color: AppTheme.danger)),
              const SizedBox(height: 8),
            ],
            AppTextField(
              controller: _password,
              label: 'New password',
              obscureText: true,
              prefixIcon: Icons.lock_outline,
            ),
            const SizedBox(height: 10),
            AppTextField(
              controller: _confirm,
              label: 'Confirm password',
              obscureText: true,
              prefixIcon: Icons.lock_outline,
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _working ? null : () => Navigator.of(context).pop(false),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _working ? null : _submit,
          child: Text(_working ? 'Saving…' : 'Reset'),
        ),
      ],
    );
  }
}
