# The release build shrinks and renames code (R8). Media3's effects are built
# from our Kotlin with Guava collections (OverlayEffect(ImmutableList)), and
# the shrinker rewrote that constructor away — every burned video failed with
# "No direct method <init>(ImmutableList)". Keep both libraries whole.
-keep class androidx.media3.** { *; }
-keep interface androidx.media3.** { *; }
-keep class com.google.common.** { *; }
-dontwarn com.google.common.**
-dontwarn androidx.media3.**
# Our own renderer classes (ScoreBar, VsScreen, VsAnim, FadeAudio) extend Media3 types.
-keep class bh.takeone.takeone_lab.** { *; }
