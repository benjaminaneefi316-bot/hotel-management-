import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

import 'package:azure_bay_mobile/src/api/api_client.dart';
import 'package:azure_bay_mobile/src/api/hotel_repository.dart';
import 'package:azure_bay_mobile/src/auth/auth_repository.dart';
import 'package:azure_bay_mobile/src/models/app_user.dart';
import 'package:azure_bay_mobile/src/models/booking.dart';
import 'package:azure_bay_mobile/src/models/front_desk.dart';
import 'package:azure_bay_mobile/src/models/room_type.dart';
import 'package:azure_bay_mobile/src/screens/dashboard_screen.dart';
import 'package:azure_bay_mobile/src/screens/hotel_screen.dart';
import 'package:azure_bay_mobile/src/screens/login_screen.dart';
import 'package:azure_bay_mobile/src/screens/payments_screen.dart';
import 'package:azure_bay_mobile/src/screens/security_screen.dart';
import 'package:azure_bay_mobile/src/screens/users_screen.dart';
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

    test('FrontDeskDay reads arrivals, departures and the stay id', () {
      Map<String, dynamic> booking(int id, String reference) => {
            'id': id,
            'reference': reference,
            'status': 'checked_in',
            'status_label': 'In house',
            'nights': 2,
            'adults': 2,
            'children': 0,
            'total_amount': 360,
            'paid_amount': 100,
            'balance': 260,
            'can_cancel': false,
            'can_check_in': false,
            'can_confirm': false,
            'stay_id': 77,
            'guest': {'full_name': 'Mei Tanaka'},
            'rooms': [],
            'payments': [],
          };

      final day = FrontDeskDay.fromJson({
        'date': '2026-10-26',
        'arrivals': [booking(1, 'AB-1')],
        'departures': [booking(2, 'AB-2')],
      });

      expect(day.arrivals.single.reference, 'AB-1');
      expect(day.departures.single.stayId, 77);
      expect(day.departures.single.guest?.fullName, 'Mei Tanaka');
      expect(day.isEmpty, isFalse);
    });
  });

  group('Staff dashboard', () {
    late ApiClient api;

    Map<String, dynamic> booking(int id, String status, String label) => {
          'id': id,
          'reference': 'AB-$id',
          'status': status,
          'status_label': label,
          'check_in_date': '2026-10-26',
          'check_out_date': '2026-10-28',
          'nights': 2,
          'adults': 2,
          'children': 0,
          'total_amount': 360,
          'paid_amount': 100,
          'balance': 260,
          'can_cancel': false,
          'can_check_in': status == 'confirmed',
          'can_confirm': status == 'pending',
          'guest': {'full_name': 'Mei Tanaka'},
          'rooms': [
            {'id': 1, 'room_number': '101', 'rate': 180, 'room_type': 'Classic'},
          ],
          'payments': [],
        };

    setUp(() {
      api = ApiClient(
        baseUrl: 'https://example.test/api',
        httpClient: MockClient((request) async {
          final path = request.url.path;
          final headers = {'content-type': 'application/json'};

          if (path.endsWith('/dashboard/summary')) {
            return http.Response(
              jsonEncode({
                'date': '2026-10-26',
                'kpi': {
                  'arrivals_today': 1,
                  'departures_today': 0,
                  'in_house': 2,
                  'occupancy_percent': 50.0,
                  'revenue_month': 1234.0,
                  'pending_bookings': 1,
                },
                'rooms': [
                  {'status': 'available', 'label': 'Available', 'count': 10},
                ],
              }),
              200,
              headers: headers,
            );
          }

          if (path.endsWith('/front-desk')) {
            return http.Response(
              jsonEncode({
                'date': '2026-10-26',
                'arrivals': [booking(1, 'confirmed', 'Confirmed')],
                'departures': [],
              }),
              200,
              headers: headers,
            );
          }

          return http.Response('{}', 200, headers: headers);
        }),
      );
    });

    Widget host(Widget child) {
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

    testWidgets('shows large actions and opens the front desk', (tester) async {
      await tester.pumpWidget(host(const DashboardScreen()));
      await tester.pumpAndSettle();

      expect(find.text('Open front desk'), findsOneWidget);
      expect(find.text('All bookings'), findsOneWidget);
      expect(find.text('Rooms'), findsOneWidget);

      await tester.tap(find.text('Open front desk'));
      await tester.pumpAndSettle();

      expect(find.text('Front desk'), findsOneWidget);
      expect(find.text('Mei Tanaka'), findsOneWidget);
      expect(find.text('Check in'), findsOneWidget);
    });
  });

  group('New feature screens', () {
    late ApiClient api;

    setUp(() {
      api = ApiClient(
        baseUrl: 'https://example.test/api',
        httpClient: MockClient((request) async {
          final path = request.url.path;
          final headers = {'content-type': 'application/json'};

          if (path.endsWith('/two-factor')) {
            return http.Response(
              jsonEncode({'enabled': false, 'channel': 'email'}),
              200,
              headers: headers,
            );
          }

          if (path.endsWith('/users/roles')) {
            return http.Response(
              jsonEncode({
                'data': [
                  {
                    'name': 'admin',
                    'label': 'Owner / admin',
                    'description': 'Full access',
                  },
                  {
                    'name': 'receptionist',
                    'label': 'Receptionist',
                    'description': 'Front desk',
                  },
                ],
              }),
              200,
              headers: headers,
            );
          }

          if (path.endsWith('/users')) {
            return http.Response(
              jsonEncode({
                'data': [
                  {
                    'id': 7,
                    'name': 'Ada Okafor',
                    'username': 'ada',
                    'email': 'ada@example.test',
                    'status': 'active',
                    'status_label': 'Active',
                    'roles': ['admin'],
                    'role_labels': ['Owner / admin'],
                    'two_factor_enabled': true,
                  },
                ],
              }),
              200,
              headers: headers,
            );
          }

          if (path.endsWith('/payments/summary')) {
            return http.Response(
              jsonEncode({
                'paid_total': 420.0,
                'pending_total': 80.0,
                'refunded_total': 0.0,
                'currency': 'USD',
                'counts': {'paid': 1, 'pending': 1},
              }),
              200,
              headers: headers,
            );
          }

          if (path.endsWith('/payments')) {
            return http.Response(
              jsonEncode({
                'data': [
                  {
                    'id': 1,
                    'amount': 420.0,
                    'currency': 'USD',
                    'status': 'paid',
                    'status_label': 'Paid',
                    'method': 'card',
                    'method_label': 'Card',
                    'booking_reference': 'AB-1001',
                    'guest_name': 'Ada Okafor',
                  },
                ],
              }),
              200,
              headers: headers,
            );
          }

          if (path.endsWith('/hotel')) {
            return http.Response(
              jsonEncode({
                'hotel': {
                  'name': 'Azure Bay Hotel',
                  'tagline': 'A quieter kind of luxury',
                  'description': 'Seafront rooms and warm service.',
                  'short_description': 'Seafront rooms in the old town.',
                  'address': '12 Harbour Road',
                  'phone': '+1 555 0100',
                  'email': 'stay@example.test',
                  'check_in_time': '15:00',
                  'check_out_time': '11:00',
                  'hero_image': '',
                  'rating': 4.8,
                  'established': 1998,
                },
                'stats': [
                  {'value': '6', 'label': 'Room types'},
                ],
                'amenities': [
                  {
                    'icon': 'pool',
                    'title': 'Sea-view pool',
                    'description': 'Open from sunrise to late.',
                  },
                ],
                'gallery': [],
                'highlights': [],
                'room_types': [],
                'featured_room_types': [],
              }),
              200,
              headers: headers,
            );
          }

          return http.Response('{}', 200, headers: headers);
        }),
      );
    });

    Widget host(Widget child) {
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

    testWidgets('security screen invites an authenticator setup',
        (tester) async {
      await tester.pumpWidget(host(const SecurityScreen()));
      await tester.pumpAndSettle();

      expect(find.text('Extra protection is off'), findsOneWidget);
      expect(find.text('Connect an authenticator app'), findsOneWidget);
    });

    testWidgets('users screen lists accounts with their roles',
        (tester) async {
      await tester.pumpWidget(host(const UsersScreen()));
      await tester.pumpAndSettle();

      expect(find.text('Ada Okafor'), findsOneWidget);
      expect(find.text('@ada · ada@example.test'), findsOneWidget);
      expect(find.text('Owner / admin'), findsOneWidget);
      expect(find.text('New user'), findsOneWidget);
    });

    testWidgets('payments screen shows totals and the ledger', (tester) async {
      await tester.pumpWidget(host(const PaymentsScreen()));
      await tester.pumpAndSettle();

      expect(find.text('COLLECTED'), findsOneWidget);
      expect(find.text('AWAITING PAYMENT'), findsOneWidget);
      expect(find.text('AB-1001 · Ada Okafor · Card'), findsOneWidget);
      expect(find.text('\$420.00'), findsWidgets);
    });

    testWidgets('hotel screen tells the same story as the website',
        (tester) async {
      await tester.pumpWidget(host(const HotelScreen()));
      await tester.pumpAndSettle();

      expect(find.text('A quieter kind of luxury'), findsOneWidget);

      await tester.scrollUntilVisible(find.text('FACILITIES'), 250);
      expect(find.text('FACILITIES'), findsOneWidget);

      await tester.scrollUntilVisible(find.text('Sea-view pool'), 250);
      expect(find.text('Sea-view pool'), findsOneWidget);

      await tester.scrollUntilVisible(find.text('Contact the hotel'), 250);
      expect(find.text('Contact the hotel'), findsOneWidget);
    });
  });
}
