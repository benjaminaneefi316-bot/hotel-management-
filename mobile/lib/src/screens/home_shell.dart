import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/session_controller.dart';
import 'catalog_screen.dart';
import 'dashboard_screen.dart';
import 'hotel_screen.dart';
import 'my_bookings_screen.dart';
import 'profile_screen.dart';
import 'rooms_screen.dart';

/// The signed-in experience. Staff get a dashboard and a room list; guests get
/// the booking catalogue. Both share the hotel website, bookings and profile.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final isStaff = context.select<SessionController, bool>((s) => s.isStaff);

    final destinations = <NavigationDestination>[
      if (isStaff)
        const NavigationDestination(
          icon: Icon(Icons.insights_outlined),
          selectedIcon: Icon(Icons.insights),
          label: 'Dashboard',
        )
      else
        const NavigationDestination(
          icon: Icon(Icons.hotel_outlined),
          selectedIcon: Icon(Icons.hotel),
          label: 'Hotel',
        ),
      if (isStaff)
        const NavigationDestination(
          icon: Icon(Icons.meeting_room_outlined),
          selectedIcon: Icon(Icons.meeting_room),
          label: 'Rooms',
        )
      else
        const NavigationDestination(
          icon: Icon(Icons.search_outlined),
          selectedIcon: Icon(Icons.search),
          label: 'Stay',
        ),
      if (isStaff)
        const NavigationDestination(
          icon: Icon(Icons.event_note_outlined),
          selectedIcon: Icon(Icons.event_note),
          label: 'Bookings',
        )
      else
        const NavigationDestination(
          icon: Icon(Icons.event_note_outlined),
          selectedIcon: Icon(Icons.event_note),
          label: 'Bookings',
        ),
      if (isStaff)
        const NavigationDestination(
          icon: Icon(Icons.storefront_outlined),
          selectedIcon: Icon(Icons.storefront),
          label: 'Hotel',
        ),
      const NavigationDestination(
        icon: Icon(Icons.person_outline),
        selectedIcon: Icon(Icons.person),
        label: 'Profile',
      ),
    ];

    final pages = <Widget>[
      if (isStaff) const DashboardScreen() else const HotelScreen(),
      if (isStaff) const RoomsScreen() else const CatalogScreen(),
      const MyBookingsScreen(),
      if (isStaff) const HotelScreen(),
      const ProfileScreen(),
    ];

    // Guard against the index being out of range if the role changes.
    final safeIndex = _index.clamp(0, pages.length - 1);

    return Scaffold(
      body: IndexedStack(index: safeIndex, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: safeIndex,
        onDestinationSelected: (value) => setState(() => _index = value),
        destinations: destinations,
      ),
    );
  }
}
