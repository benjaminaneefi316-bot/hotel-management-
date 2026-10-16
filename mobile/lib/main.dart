import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'src/api/api_client.dart';
import 'src/api/hotel_repository.dart';
import 'src/auth/auth_repository.dart';
import 'src/screens/home_shell.dart';
import 'src/screens/login_screen.dart';
import 'src/screens/splash_screen.dart';
import 'src/state/session_controller.dart';
import 'src/theme/app_theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  final api = ApiClient();

  runApp(
    AzureBayApp(
      session: SessionController(AuthRepository(api: api)),
      repository: HotelRepository(api),
    ),
  );
}

/// Root widget: wires up the two shared objects (session + data repository)
/// and hands the whole app to the `provider` package.
class AzureBayApp extends StatelessWidget {
  const AzureBayApp({
    super.key,
    required this.session,
    required this.repository,
  });

  final SessionController session;
  final HotelRepository repository;

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider<SessionController>.value(value: session),
        Provider<HotelRepository>.value(value: repository),
      ],
      child: MaterialApp(
        title: 'Azure Bay Hotel',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        home: const _AuthGate(),
      ),
    );
  }
}

/// Decides which screen to show: a stored session is restored first, then the
/// user lands on either the signed-in shell or the login screen.
class _AuthGate extends StatefulWidget {
  const _AuthGate();

  @override
  State<_AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<_AuthGate> {
  late final Future<void> _restore;

  @override
  void initState() {
    super.initState();
    // Kick off session restoration exactly once.
    _restore = context.read<SessionController>().restore();
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<SessionController>();

    return FutureBuilder<void>(
      future: _restore,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const SplashScreen();
        }
        return session.isSignedIn ? const HomeShell() : const LoginScreen();
      },
    );
  }
}
