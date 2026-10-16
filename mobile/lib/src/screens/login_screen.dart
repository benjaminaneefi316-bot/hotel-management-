import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_client.dart';
import '../state/session_controller.dart';
import '../theme/app_theme.dart';
import '../widgets/app_widgets.dart';
import 'two_factor_screen.dart';

/// Email + password sign-in. Completes directly if 2FA is disabled, otherwise
/// pushes the one-time-code screen.
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    final session = context.read<SessionController>();
    final result = await session.signIn(
      _email.text.trim(),
      _password.text,
    );

    if (!mounted || result == null) {
      return; // error is surfaced from the session below
    }

    if (result.requiresTwoFactor) {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => TwoFactorScreen(challenge: result),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<SessionController>();

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const _Brand(),
                  const SizedBox(height: 28),
                  Panel(
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Text(
                            'Sign in',
                            style: TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w700,
                              color: AppTheme.navy,
                            ),
                          ),
                          const SizedBox(height: 4),
                          const Text(
                            'Front-desk staff and guests use the same portal.',
                            style: TextStyle(color: AppTheme.muted, fontSize: 13),
                          ),
                          const SizedBox(height: 20),
                          AppTextField(
                            controller: _email,
                            label: 'Email address',
                            hint: 'you@example.com',
                            keyboardType: TextInputType.emailAddress,
                            prefixIcon: Icons.alternate_email,
                            textInputAction: TextInputAction.next,
                            validator: (value) {
                              final v = (value ?? '').trim();
                              if (v.isEmpty) {
                                return 'Enter your email address';
                              }
                              if (!v.contains('@')) {
                                return 'Enter a valid email address';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 16),
                          AppTextField(
                            controller: _password,
                            label: 'Password',
                            obscureText: true,
                            prefixIcon: Icons.lock_outline,
                            textInputAction: TextInputAction.done,
                            onSubmitted: (_) => _submit(),
                            validator: (value) =>
                                (value ?? '').isEmpty ? 'Enter your password' : null,
                          ),
                          if (session.error != null) ...[
                            const SizedBox(height: 16),
                            _ErrorBanner(message: session.error!),
                          ],
                          const SizedBox(height: 22),
                          FilledButton(
                            onPressed: session.busy ? null : _submit,
                            child: session.busy
                                ? const SizedBox(
                                    height: 20,
                                    width: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Colors.white,
                                    ),
                                  )
                                : const Text('Continue'),
                          ),
                          const SizedBox(height: 14),
                          const Text(
                            'Server: ${ApiClient.defaultBaseUrl}',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: AppTheme.muted,
                              fontSize: 11,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'A one-time code is emailed to you as a second factor.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppTheme.muted, fontSize: 12),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Brand extends StatelessWidget {
  const _Brand();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          height: 72,
          width: 72,
          decoration: BoxDecoration(
            color: AppTheme.navy,
            borderRadius: BorderRadius.circular(20),
          ),
          child: const Icon(Icons.apartment, color: AppTheme.gold, size: 36),
        ),
        const SizedBox(height: 14),
        const Text(
          'Azure Bay Hotel',
          style: TextStyle(
            fontSize: 24,
            fontWeight: FontWeight.w700,
            color: AppTheme.navy,
          ),
        ),
        const SizedBox(height: 2),
        const Text(
          'Management System',
          style: TextStyle(color: AppTheme.muted, letterSpacing: 1.2),
        ),
      ],
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFB23A48).withAlpha(20),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFB23A48).withAlpha(80)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline, color: Color(0xFFB23A48), size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: Color(0xFF8E2B36), fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}
