import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../auth/auth_repository.dart';
import '../state/session_controller.dart';
import '../theme/app_theme.dart';
import '../widgets/app_widgets.dart';

/// Second step of sign-in: the six-digit code emailed to the user.
class TwoFactorScreen extends StatefulWidget {
  const TwoFactorScreen({super.key, required this.challenge});

  final LoginResult challenge;

  @override
  State<TwoFactorScreen> createState() => _TwoFactorScreenState();
}

class _TwoFactorScreenState extends State<TwoFactorScreen> {
  final _code = TextEditingController();
  String? _localError;
  String? _devCode;

  /// True when the code must be read off Google Authenticator / Authy / etc.
  /// rather than out of an email.
  bool get _usesAuthenticatorApp => widget.challenge.usesAuthenticatorApp;

  @override
  void initState() {
    super.initState();
    _devCode = widget.challenge.devCode;
  }

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    final token = widget.challenge.challengeToken;
    final code = _code.text.trim();

    if (token == null) {
      setState(() => _localError = 'The login challenge expired. Please sign in again.');
      return;
    }
    if (code.length < 4) {
      setState(() => _localError = _usesAuthenticatorApp
          ? 'Enter the 6-digit code shown in your authenticator app.'
          : 'Enter the code from your email.');
      return;
    }

    setState(() => _localError = null);

    final session = context.read<SessionController>();
    final ok = await session.verifyCode(token, code);

    if (!mounted) {
      return;
    }
    if (ok) {
      // Drop this route; the root gate swaps to the signed-in shell.
      Navigator.of(context).popUntil((route) => route.isFirst);
    }
  }

  Future<void> _resend() async {
    final token = widget.challenge.challengeToken;
    if (token == null) {
      return;
    }

    final session = context.read<SessionController>();
    final devCode = await session.resendCode(token);

    if (!mounted) {
      return;
    }
    if (devCode != null) {
      setState(() => _devCode = devCode);
    }
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('A new code has been sent.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<SessionController>();
    final error = _localError ?? session.error;
    final email = widget.challenge.email ?? 'your email address';

    return Scaffold(
      appBar: AppBar(title: const Text('Verification')),
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
                  Icon(
                    _usesAuthenticatorApp
                        ? Icons.phone_iphone
                        : Icons.mark_email_read_outlined,
                    size: 44,
                    color: AppTheme.gold,
                  ),
                  const SizedBox(height: 16),
                  Text(
                    _usesAuthenticatorApp
                        ? 'Open your authenticator app'
                        : 'Check your inbox',
                    textAlign: TextAlign.center,
                    style: AppTheme.display(21),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _usesAuthenticatorApp
                        ? 'Enter the 6-digit code it shows for Azure Bay Hotel. '
                            'The code refreshes every 30 seconds.'
                        : 'We emailed a one-time code to $email.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: AppTheme.muted),
                  ),
                  const SizedBox(height: 24),
                  Panel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        AppTextField(
                          controller: _code,
                          label: 'One-time code',
                          hint: '123456',
                          keyboardType: TextInputType.number,
                          prefixIcon: Icons.password,
                          onSubmitted: (_) => _verify(),
                          validator: null,
                        ),
                        if (_devCode != null) ...[
                          const SizedBox(height: 14),
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: AppTheme.gold.withAlpha(28),
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: AppTheme.gold.withAlpha(90)),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.science_outlined,
                                    size: 16, color: AppTheme.charcoal),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    'Development code: $_devCode',
                                    style: const TextStyle(fontSize: 13),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                        if (error != null) ...[
                          const SizedBox(height: 14),
                          Text(
                            error,
                            style: const TextStyle(
                                color: AppTheme.danger, fontSize: 13),
                          ),
                        ],
                        const SizedBox(height: 20),
                        FilledButton(
                          onPressed: context.watch<SessionController>().busy
                              ? null
                              : _verify,
                          child: const Text('Verify and continue'),
                        ),
                        // Nothing is emailed for authenticator-app accounts,
                        // so offering "Resend" there would be a lie.
                        if (!_usesAuthenticatorApp) ...[
                          const SizedBox(height: 8),
                          TextButton(
                            onPressed: _resend,
                            child: const Text('Resend code'),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextButton.icon(
                    onPressed: () => Navigator.of(context).maybePop(),
                    icon: const Icon(Icons.arrow_back, size: 18),
                    label: const Text('Use a different account'),
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
