import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/hotel_repository.dart';
import '../models/room_type.dart';
import '../theme/app_theme.dart';
import '../util/assets.dart';
import '../widgets/app_widgets.dart';
import 'room_type_detail_screen.dart';

/// The public room-class catalogue (the guest's home tab).
class CatalogScreen extends StatefulWidget {
  const CatalogScreen({super.key});

  @override
  State<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends State<CatalogScreen> {
  late Future<List<RoomType>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<RoomType>> _load() =>
      context.read<HotelRepository>().roomTypes();

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Azure Bay Hotel'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _reload,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder<List<RoomType>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return EmptyState(
              icon: Icons.wifi_off,
              title: 'Could not load the room list',
              message: '${snapshot.error}',
              onRetry: _reload,
            );
          }

          final types = snapshot.data ?? const <RoomType>[];
          if (types.isEmpty) {
            return const EmptyState(
              icon: Icons.hotel_outlined,
              title: 'No room classes yet',
              message: 'The property has not published any rooms.',
              onRetry: null,
            );
          }

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
              itemCount: types.length + 1,
              separatorBuilder: (_, __) => const SizedBox(height: 16),
              itemBuilder: (context, index) {
                if (index == 0) {
                  return const _Intro();
                }
                return _RoomTypeCard(type: types[index - 1]);
              },
            ),
          );
        },
      ),
    );
  }
}

class _Intro extends StatelessWidget {
  const _Intro();

  @override
  Widget build(BuildContext context) {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Find your room',
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.w700,
            color: AppTheme.navy,
          ),
        ),
        SizedBox(height: 4),
        Text(
          'Tap a room class to see details and check availability.',
          style: TextStyle(color: AppTheme.muted),
        ),
      ],
    );
  }
}

class _RoomTypeCard extends StatelessWidget {
  const _RoomTypeCard({required this.type});

  final RoomType type;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => RoomTypeDetailScreen(type: type),
          ),
        ),
        child: Container(
          decoration: AppTheme.panel(),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                height: 170,
                width: double.infinity,
                child: RemoteImage(url: assetUrl(type.imageUrl)),
              ),
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            type.name,
                            style: const TextStyle(
                              fontSize: 17,
                              fontWeight: FontWeight.w700,
                              color: AppTheme.navy,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '${type.basePrice.toStringAsFixed(0)}/night',
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            color: AppTheme.navySoft,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 14,
                      runSpacing: 6,
                      children: [
                        _Meta(icon: Icons.bed_outlined, text: type.bedType),
                        _Meta(
                          icon: Icons.people_outline,
                          text: 'Sleeps ${type.capacity}',
                        ),
                      ],
                    ),
                    if (type.amenities.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: type.amenities
                            .take(4)
                            .map((a) => _AmenityChip(label: a))
                            .toList(growable: false),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: AppTheme.muted),
        const SizedBox(width: 4),
        Text(text, style: const TextStyle(color: AppTheme.muted, fontSize: 13)),
      ],
    );
  }
}

class _AmenityChip extends StatelessWidget {
  const _AmenityChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: AppTheme.cream,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppTheme.line),
      ),
      child: Text(label, style: const TextStyle(fontSize: 12)),
    );
  }
}
