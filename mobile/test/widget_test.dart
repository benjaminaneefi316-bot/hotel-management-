import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

import 'package:azure_bay_mobile/src/api/api_client.dart';
import 'package:azure_bay_mobile/src/api/hotel_repository.dart';
import 'package:azure_bay_mobile/src/auth/auth_repository.dart';
import 'package:azure_bay_mobile/src/models/app_user.dart';
import 'package:azure_bay_mobile/src/models/booking.dart';
import 'package:azure_bay_mobile/src/models/room_type.dart';
import 'package:azure_bay_mobile/src/screens/login_screen.dart';
import 'package:azure_bay_mobile/src/state/session_controller.dart';
import 'package:azure_bay_mobile/src/theme/app_theme.dart';

/// Wraps [child] with the same providers the real app supplies.
Widget _host(Widget child) {
  final api = ApiClient(baseUrl: 'http://10.0.2.2:8000/api');
  return MultiProvider(
    providers: [
      ChangeNotifierProvider<SessionController>(
        create: (_) => SessionController(AuthRepository(api: api)),
      ),
      Provider<HotelRepository>(create: (_) => HotelRepository(api)),
    ],
    child: MaterialApp(theme: AppTheme.light(), home: child),
  );
}

void main() {
  group('Login screen', () {
    testWidgets('renders the brand and the sign-in form', (tester) async {
      await tester.pumpWidget(_host(const LoginScreen()));

      expect(find.text('Azure Bay'), findsOneWidget);
      expect(find.text('HOTEL & RESORT'), findsOneWidget);
      expect(find.text('Welcome back'), findsOneWidget);
      expect(find.text('Continue'), findsOneWidget);
      expect(find.text('Email address'), findsOneWidget);
      expect(find.text('Password'), findsOneWidget);
    });

    testWidgets('validates an empty submission', (tester) async {
      await tester.pumpWidget(_host(const LoginScreen()));

      await tester.tap(find.text('Continue'));
      await tester.pump();

      expect(find.text('Enter your email address'), findsOneWidget);
      expect(find.text('Enter your password'), findsOneWidget);
    });

    testWidgets('rejects a malformed email', (tester) async {
      await tester.pumpWidget(_host(const LoginScreen()));

      await tester.enterText(
        find.byType(TextFormField).first,
        'not-an-email',
      );
      await tester.tap(find.text('Continue'));
      await tester.pump();

      expect(find.text('Enter a valid email address'), findsOneWidget);
    });
  });

  group('ApiClient.resolveAsset', () {
    test('prefixes a root-relative path with the origin', () {
      final url = ApiClient.resolveAsset(
        '/assets/img/room-classic.jpg',
        baseUrl: 'http://10.0.2.2:8000/api',
      );
      expect(url, 'http://10.0.2.2:8000/assets/img/room-classic.jpg');
    });

    test('leaves an already-absolute URL untouched', () {
      final url = ApiClient.resolveAsset(
        'https://cdn.example.com/a.jpg',
        baseUrl: 'http://10.0.2.2:8000/api',
      );
      expect(url, 'https://cdn.example.com/a.jpg');
    });

    test('returns an empty string for null/blank input', () {
      expect(ApiClient.resolveAsset(null), '');
      expect(ApiClient.resolveAsset('   '), '');
    });

    test('works when the base URL has no /api suffix or trailing slash', () {
      expect(
        ApiClient.resolveAsset('/a.png', baseUrl: 'http://host:8000/'),
        'http://host:8000/a.png',
      );
    });
  });

  group('Models', () {
    test('AppUser derives initials and parses optional phone', () {
      final user = AppUser.fromJson({
        'id': 1,
        'name': 'Benjamin Aneefi',
        'email': 'benjaminaneefi316@gmail.com',
        'phone': '0539460356',
        'roles': ['admin'],
        'is_staff': true,
      });

      expect(user.initials, 'BA');
      expect(user.phone, '0539460356');
      expect(user.isStaff, isTrue);
    });

    test('RoomType reads catalogue fields', () {
      final type = RoomType.fromJson({
        'id': 3,
        'name': 'Classic King',
        'slug': 'classic-king',
        'bed_type': 'King',
        'capacity': 2,
        'base_price': 180,
        'amenities': ['Wi-Fi', 'Minibar'],
        'image_url': '/assets/img/room-classic.jpg',
      });

      expect(type.capacity, 2);
      expect(type.basePrice, 180);
      expect(type.amenities, contains('Wi-Fi'));
      expect(type.imageUrl, '/assets/img/room-classic.jpg');
    });

    test('Booking reads nested rooms and payments', () {
      final booking = Booking.fromJson({
        'id': 9,
        'reference': 'AB-2026-0009',
        'status': 'confirmed',
        'status_label': 'Confirmed',
        'nights': 2,
        'adults': 2,
        'children': 0,
        'total_amount': 360,
        'paid_amount': 100,
        'balance': 260,
        'can_cancel': true,
        'can_check_in': false,
        'rooms': [
          {'id': 1, 'room_number': '101', 'rate': 180, 'room_type': 'Classic King'},
        ],
        'payments': [
          {
            'id': 5,
            'amount': 100,
            'currency': 'USD',
            'status': 'partial',
            'status_label': 'Partial',
          },
        ],
      });

      expect(booking.reference, 'AB-2026-0009');
      expect(booking.rooms.single.roomNumber, '101');
      expect(booking.payments.single.currency, 'USD');
      expect(booking.canCancel, isTrue);
      expect(booking.balance, 260);
    });
  });
}
