import 'package:flutter/material.dart';

import '../models/room_type.dart';
import '../theme/app_theme.dart';
import '../util/assets.dart';
import '../widgets/app_widgets.dart';
import 'booking_form_screen.dart';

/// Full details for one room class, with a call to action that opens the
/// booking form.
class RoomTypeDetailScreen extends StatelessWidget {
  const RoomTypeDetailScreen({super.key, required this.type});

  final RoomType type;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(type.name)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: SizedBox(
              height: 220,
              width: double.infinity,
              child: RemoteImage(url: assetUrl(type.imageUrl)),
            ),
          ),
          const SizedBox(height: 20),
          Text(
            type.name,
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w700,
              color: AppTheme.navy,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            '${type.basePrice.toStringAsFixed(0)} per night',
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: AppTheme.navySoft,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _FactChip(icon: Icons.bed_outlined, label: type.bedType),
              _FactChip(
                icon: Icons.people_outline,
                label: 'Sleeps ${type.capacity}',
              ),
              if (type.code != null)
                _FactChip(icon: Icons.qr_code_2, label: type.code!),
            ],
          ),
          if (type.description != null && type.description!.trim().isNotEmpty) ...[
            const SizedBox(height: 22),
            const _SectionTitle('About this room'),
            const SizedBox(height: 8),
            Text(
              type.description!,
              style: const TextStyle(color: AppTheme.ink, height: 1.5),
            ),
          ],
          if (type.amenities.isNotEmpty) ...[
            const SizedBox(height: 22),
            const _SectionTitle('Amenities'),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: type.amenities
                  .map(
                    (a) => Chip(
                      label: Text(a),
                      backgroundColor: Colors.white,
                      side: const BorderSide(color: AppTheme.line),
                    ),
                  )
                  .toList(growable: false),
            ),
          ],
          const SizedBox(height: 24),
          FilledButton.icon(
            icon: const Icon(Icons.event_available),
            label: const Text('Check availability and book'),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => BookingFormScreen(type: type),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w700,
        color: AppTheme.navy,
      ),
    );
  }
}

class _FactChip extends StatelessWidget {
  const _FactChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.line),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: AppTheme.gold),
          const SizedBox(width: 6),
          Text(label, style: const TextStyle(fontSize: 13)),
        ],
      ),
    );
  }
}
