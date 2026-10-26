import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_exception.dart';
import '../api/hotel_repository.dart';
import '../models/hotel_content.dart';
import '../models/room_type.dart';
import '../theme/app_theme.dart';
import '../util/assets.dart';
import '../util/formatters.dart';
import '../widgets/app_widgets.dart';
import 'catalog_screen.dart';
import 'room_type_detail_screen.dart';

/// The hotel's public website, inside the app.
///
/// Same story and imagery as the website: hero, welcome, amenities, rooms,
/// gallery and contact. It needs no account data, so staff and guests see the
/// same page.
class HotelScreen extends StatefulWidget {
  const HotelScreen({super.key});

  @override
  State<HotelScreen> createState() => _HotelScreenState();
}

class _HotelScreenState extends State<HotelScreen> {
  late Future<HotelContent> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<HotelContent> _load() =>
      context.read<HotelRepository>().hotelContent();

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
      body: FutureBuilder<HotelContent>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError || !snapshot.hasData) {
            return EmptyState(
              icon: Icons.wifi_off,
              title: 'Could not load the hotel page',
              message: '${snapshot.error}',
              onRetry: _reload,
            );
          }

          final content = snapshot.data!;

          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.only(bottom: 28),
              children: [
                _Hero(hotel: content.hotel, onExplore: () => _openCatalog()),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                  child: Row(
                    children: content.stats
                        .take(4)
                        .map(
                          (stat) => Expanded(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 4),
                              child: Container(
                                padding: const EdgeInsets.symmetric(vertical: 12),
                                decoration: AppTheme.panel(elevated: false),
                                child: Column(
                                  children: [
                                    Text(
                                      stat.value,
                                      style: AppTheme.display(
                                        22,
                                        weight: FontWeight.w700,
                                      ),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      stat.label.toUpperCase(),
                                      textAlign: TextAlign.center,
                                      style: const TextStyle(
                                        fontSize: 8.5,
                                        letterSpacing: 0.8,
                                        color: AppTheme.muted,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        )
                        .toList(growable: false),
                  ),
                ),
                _Section(
                  eyebrow: 'Welcome to Azure Bay',
                  title: 'A stay shaped around you',
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        content.hotel.description,
                        style: const TextStyle(
                          color: AppTheme.muted,
                          height: 1.5,
                        ),
                      ),
                      const SizedBox(height: 16),
                      Panel(
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          children: [
                            _ContactLine(
                              icon: Icons.location_on_outlined,
                              text: content.hotel.address,
                            ),
                            const SizedBox(height: 10),
                            _ContactLine(
                              icon: Icons.phone_outlined,
                              text: content.hotel.phone,
                            ),
                            const SizedBox(height: 10),
                            _ContactLine(
                              icon: Icons.mail_outline,
                              text: content.hotel.email,
                            ),
                            const Divider(height: 20),
                            Row(
                              children: [
                                Expanded(
                                  child: _ContactLine(
                                    icon: Icons.login,
                                    text: 'Check-in ${content.hotel.checkInTime}',
                                  ),
                                ),
                                Expanded(
                                  child: _ContactLine(
                                    icon: Icons.logout,
                                    text: 'Check-out ${content.hotel.checkOutTime}',
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                if (content.highlights.isNotEmpty)
                  _Section(
                    eyebrow: 'The hotel',
                    title: 'Quiet luxury by the water',
                    child: Column(
                      children: content.highlights
                          .map(
                            (highlight) => Padding(
                              padding: const EdgeInsets.only(bottom: 14),
                              child: ClipRRect(
                                borderRadius:
                                    BorderRadius.circular(AppTheme.radius),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    SizedBox(
                                      height: 180,
                                      width: double.infinity,
                                      child: RemoteImage(url: highlight.image),
                                    ),
                                    Container(
                                      decoration: AppTheme.panel(),
                                      padding: const EdgeInsets.all(14),
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            highlight.title,
                                            style: AppTheme.display(18),
                                          ),
                                          const SizedBox(height: 6),
                                          Text(
                                            highlight.body,
                                            style: const TextStyle(
                                              color: AppTheme.muted,
                                              height: 1.45,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          )
                          .toList(growable: false),
                    ),
                  ),
                _Section(
                  eyebrow: 'Facilities',
                  title: 'Everything on the doorstep',
                  child: Column(
                    children: content.amenities
                        .map(
                          (amenity) => Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: Container(
                              padding: const EdgeInsets.all(12),
                              decoration: AppTheme.panel(elevated: false),
                              child: Row(
                                children: [
                                  Container(
                                    height: 40,
                                    width: 40,
                                    decoration: BoxDecoration(
                                      color: AppTheme.sand,
                                      shape: BoxShape.circle,
                                      border: Border.all(color: AppTheme.border),
                                    ),
                                    child: Icon(
                                      _amenityIcon(amenity.icon),
                                      color: AppTheme.gold,
                                      size: 20,
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          amenity.title,
                                          style: const TextStyle(
                                            fontWeight: FontWeight.w700,
                                            color: AppTheme.charcoal,
                                          ),
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          amenity.description,
                                          style: const TextStyle(
                                            color: AppTheme.muted,
                                            fontSize: 12.5,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        )
                        .toList(growable: false),
                  ),
                ),
                if (content.roomTypes.isNotEmpty)
                  _Section(
                    eyebrow: 'Rooms & suites',
                    title: 'Six ways to stay',
                    child: Column(
                      children: content.roomTypes
                          .map(
                            (type) => Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: _RoomRow(type: type),
                            ),
                          )
                          .toList(growable: false),
                    ),
                  ),
                if (content.gallery.isNotEmpty)
                  _Section(
                    eyebrow: 'Gallery',
                    title: 'A look around',
                    child: GridView.count(
                      crossAxisCount: 2,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      crossAxisSpacing: 8,
                      mainAxisSpacing: 8,
                      childAspectRatio: 1,
                      children: content.gallery
                          .map(
                            (image) => ClipRRect(
                              borderRadius: BorderRadius.circular(12),
                              child: Stack(
                                fit: StackFit.expand,
                                children: [
                                  RemoteImage(url: image.url),
                                  Positioned(
                                    left: 0,
                                    right: 0,
                                    bottom: 0,
                                    child: Container(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 8,
                                        vertical: 6,
                                      ),
                                      color: AppTheme.charcoal.withAlpha(160),
                                      child: Text(
                                        image.caption,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontSize: 11,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          )
                          .toList(growable: false),
                    ),
                  ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                  child: Container(
                    decoration: AppTheme.panel(color: AppTheme.charcoal),
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      children: [
                        const Text(
                          'PLAN YOUR STAY',
                          style: TextStyle(
                            color: AppTheme.gold,
                            fontSize: 10.5,
                            letterSpacing: 3,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Questions? Call or email us, or send a note below.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Colors.white70, height: 1.4),
                        ),
                        const SizedBox(height: 14),
                        SizedBox(
                          width: double.infinity,
                          height: 52,
                          child: FilledButton.icon(
                            style: FilledButton.styleFrom(
                              backgroundColor: AppTheme.gold,
                              foregroundColor: AppTheme.charcoal,
                            ),
                            onPressed: () => Navigator.of(context).push(
                              MaterialPageRoute<void>(
                                builder: (_) => ContactScreen(
                                  hotel: content.hotel,
                                ),
                              ),
                            ),
                            icon: const Icon(Icons.mail_outline),
                            label: const Text('Contact the hotel'),
                          ),
                        ),
                        const SizedBox(height: 10),
                        SizedBox(
                          width: double.infinity,
                          height: 52,
                          child: OutlinedButton.icon(
                            style: OutlinedButton.styleFrom(
                              foregroundColor: Colors.white,
                              side: const BorderSide(color: Colors.white24),
                            ),
                            onPressed: _openCatalog,
                            icon: const Icon(Icons.calendar_month),
                            label: const Text('Check availability'),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  void _openCatalog() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const _CatalogPage()),
    );
  }

  IconData _amenityIcon(String key) {
    switch (key) {
      case 'pool':
        return Icons.pool;
      case 'spa':
        return Icons.spa_outlined;
      case 'dining':
        return Icons.restaurant_outlined;
      case 'wifi':
        return Icons.wifi;
      case 'parking':
        return Icons.local_parking;
      default:
        return Icons.support_agent;
    }
  }
}

/// The catalogue already has its own Scaffold; this wrapper keeps the back
/// button so "Check availability" from the hotel page feels like a detail page.
class _CatalogPage extends StatelessWidget {
  const _CatalogPage();

  @override
  Widget build(BuildContext context) {
    return const CatalogScreen();
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.hotel, required this.onExplore});

  final HotelInfo hotel;
  final VoidCallback onExplore;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 300,
      child: Stack(
        fit: StackFit.expand,
        children: [
          RemoteImage(url: hotel.heroImage),
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.centerLeft,
                end: Alignment.centerRight,
                colors: [Color(0xE61F2933), Color(0x331F2933)],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                Text(
                  'AZURE BAY · EST. ${hotel.established}',
                  style: AppTheme.eyebrow.copyWith(color: AppTheme.goldSoft),
                ),
                const SizedBox(height: 8),
                Text(
                  hotel.tagline,
                  style: AppTheme.display(
                    30,
                    weight: FontWeight.w700,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  hotel.shortDescription,
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white70, height: 1.4),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    const Icon(Icons.star, color: AppTheme.gold, size: 16),
                    const SizedBox(width: 4),
                    Text(
                      '${hotel.rating} / 5 guest rating',
                      style: const TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                      ),
                    ),
                    const Spacer(),
                    TextButton(
                      onPressed: onExplore,
                      style: TextButton.styleFrom(
                        foregroundColor: AppTheme.goldSoft,
                      ),
                      child: const Text('Explore rooms'),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({
    required this.eyebrow,
    required this.title,
    required this.child,
  });

  final String eyebrow;
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(eyebrow.toUpperCase(), style: AppTheme.eyebrow),
          const SizedBox(height: 6),
          Text(title, style: AppTheme.display(22)),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _ContactLine extends StatelessWidget {
  const _ContactLine({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 17, color: AppTheme.gold),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(fontSize: 13, color: AppTheme.text),
          ),
        ),
      ],
    );
  }
}

class _RoomRow extends StatelessWidget {
  const _RoomRow({required this.type});

  final RoomType type;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(AppTheme.radius),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppTheme.radius),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => RoomTypeDetailScreen(type: type),
          ),
        ),
        child: Container(
          decoration: AppTheme.panel(),
          clipBehavior: Clip.antiAlias,
          child: Row(
            children: [
              SizedBox(
                width: 104,
                height: 92,
                child: RemoteImage(url: assetUrl(type.imageUrl)),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        type.name,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 15,
                          color: AppTheme.charcoal,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${type.bedType} · sleeps ${type.capacity}',
                        style: const TextStyle(
                          color: AppTheme.muted,
                          fontSize: 12.5,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        '${Fmt.money(type.basePrice)} / night',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: AppTheme.slate,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const Icon(Icons.chevron_right, color: AppTheme.muted),
              const SizedBox(width: 8),
            ],
          ),
        ),
      ),
    );
  }
}

/// The public contact form. Mirrors the website: the enquiry is logged by the
/// server and the guest receives an on-screen confirmation.
class ContactScreen extends StatefulWidget {
  const ContactScreen({super.key, required this.hotel});

  final HotelInfo hotel;

  @override
  State<ContactScreen> createState() => _ContactScreenState();
}

class _ContactScreenState extends State<ContactScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _subject = TextEditingController();
  final _message = TextEditingController();
  bool _working = false;
  String? _error;
  String? _sent;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _subject.dispose();
    _message.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _working = true;
      _error = null;
    });

    try {
      final confirmation = await context.read<HotelRepository>().sendContactEnquiry(
            name: _name.text.trim(),
            email: _email.text.trim(),
            phone: _phone.text.trim(),
            subject: _subject.text.trim(),
            message: _message.text.trim(),
          );
      if (mounted) {
        setState(() {
          _sent = confirmation;
          _message.clear();
        });
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
      appBar: AppBar(title: const Text('Contact')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Panel(
              child: Column(
                children: [
                  _ContactLine(icon: Icons.location_on_outlined, text: widget.hotel.address),
                  const SizedBox(height: 10),
                  _ContactLine(icon: Icons.phone_outlined, text: widget.hotel.phone),
                  const SizedBox(height: 10),
                  _ContactLine(icon: Icons.mail_outline, text: widget.hotel.email),
                  const Divider(height: 22),
                  const _ContactLine(
                    icon: Icons.schedule,
                    text: 'Reception open 24 hours, every day',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            if (_sent != null) ...[
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppTheme.success.withAlpha(18),
                  borderRadius: BorderRadius.circular(AppTheme.radiusSm),
                  border: Border.all(color: AppTheme.success.withAlpha(70)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.check_circle_outline,
                        color: AppTheme.success, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _sent!,
                        style: const TextStyle(color: AppTheme.success),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
            ],
            if (_error != null) ...[
              Text(_error!, style: const TextStyle(color: AppTheme.danger)),
              const SizedBox(height: 12),
            ],
            AppTextField(
              controller: _name,
              label: 'Your name',
              prefixIcon: Icons.person_outline,
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'Enter your name.' : null,
            ),
            const SizedBox(height: 12),
            AppTextField(
              controller: _email,
              label: 'Email',
              keyboardType: TextInputType.emailAddress,
              prefixIcon: Icons.mail_outline,
              validator: (v) {
                final value = v?.trim() ?? '';
                return (!value.contains('@') || !value.contains('.'))
                    ? 'Enter a valid email address.'
                    : null;
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
            AppTextField(
              controller: _subject,
              label: 'Subject (optional)',
              prefixIcon: Icons.subject,
            ),
            const SizedBox(height: 12),
            AppTextField(
              controller: _message,
              label: 'Message',
              maxLines: 5,
              validator: (v) {
                final value = v?.trim() ?? '';
                if (value.length < 10) {
                  return 'Please write at least 10 characters.';
                }
                return null;
              },
            ),
            const SizedBox(height: 16),
            SizedBox(
              height: 52,
              child: FilledButton.icon(
                onPressed: _working ? null : _send,
                icon: const Icon(Icons.send_outlined),
                label: Text(_working ? 'Sending…' : 'Send enquiry'),
              ),
            ),
            const SizedBox(height: 10),
            const Text(
              'Your message is passed to the reservations team. No card details '
              'are ever requested here.',
              style: TextStyle(color: AppTheme.muted, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }
}
