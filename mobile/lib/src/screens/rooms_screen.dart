import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/hotel_repository.dart';
import '../models/room.dart';
import '../theme/app_theme.dart';
import '../util/assets.dart';
import '../util/formatters.dart';
import '../widgets/app_widgets.dart';

/// Staff room list with a housekeeping-status filter.
class RoomsScreen extends StatefulWidget {
  const RoomsScreen({super.key});

  @override
  State<RoomsScreen> createState() => _RoomsScreenState();
}

class _RoomsScreenState extends State<RoomsScreen> {
  static const List<({String value, String label})> _filters = [
    (value: '', label: 'All'),
    (value: 'available', label: 'Available'),
    (value: 'occupied', label: 'Occupied'),
    (value: 'cleaning', label: 'Cleaning'),
    (value: 'maintenance', label: 'Maintenance'),
  ];

  String _status = '';
  late Future<List<Room>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<Room>> _load() =>
      context.read<HotelRepository>().rooms(status: _status.isEmpty ? null : _status);

  void _reload() => setState(() => _future = _load());

  void _setStatus(String status) {
    setState(() {
      _status = status;
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Rooms'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _reload,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: Column(
        children: [
          SizedBox(
            height: 54,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              children: _filters
                  .map(
                    (f) => Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(f.label),
                        selected: _status == f.value,
                        onSelected: (_) => _setStatus(f.value),
                      ),
                    ),
                  )
                  .toList(growable: false),
            ),
          ),
          Expanded(
            child: FutureBuilder<List<Room>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError) {
                  return EmptyState(
                    icon: Icons.wifi_off,
                    title: 'Could not load rooms',
                    message: '${snapshot.error}',
                    onRetry: _reload,
                  );
                }

                final rooms = snapshot.data ?? const <Room>[];
                if (rooms.isEmpty) {
                  return const EmptyState(
                    icon: Icons.meeting_room_outlined,
                    title: 'No rooms match',
                    message: 'Try a different status filter.',
                  );
                }

                return RefreshIndicator(
                  onRefresh: () async => _reload(),
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    itemCount: rooms.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) => _RoomTile(room: rooms[index]),
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

class _RoomTile extends StatelessWidget {
  const _RoomTile({required this.room});

  final Room room;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: AppTheme.panel(),
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: SizedBox(
              height: 56,
              width: 56,
              child: RemoteImage(url: assetUrl(room.imageUrl)),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Room ${room.roomNumber}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    color: AppTheme.navy,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  room.roomTypeName ?? 'Unassigned class',
                  style: const TextStyle(color: AppTheme.muted, fontSize: 13),
                ),
                const SizedBox(height: 2),
                Text(
                  '${Fmt.money(room.effectiveRate)} / night',
                  style: const TextStyle(color: AppTheme.muted, fontSize: 12),
                ),
              ],
            ),
          ),
          StatusPill(
            label: room.statusLabel,
            color: StatusPill.colorFor(room.status),
          ),
        ],
      ),
    );
  }
}
