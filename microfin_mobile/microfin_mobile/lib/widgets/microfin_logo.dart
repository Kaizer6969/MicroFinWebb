import '../theme.dart';
import 'package:flutter/material.dart';

class MicroFinLogo extends StatelessWidget {
  final double size;

  MicroFinLogo({
    super.key,
    this.size = 100,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF3B82F6), Color(0xFF1D4ED8)],
        ),
        borderRadius: BorderRadius.circular(size * 0.25),
        boxShadow: [
          BoxShadow(
            color: Color(0xFF1D4ED8).withOpacity(0.35),
            blurRadius: size * 0.2,
            offset: Offset(0, size * 0.08),
          ),
        ],
      ),
      child: Center(
        child: Icon(
          Icons.account_balance_outlined,
          color: AppColors.card,
          size: size * 0.55,
        ),
      ),
    );
  }
}


