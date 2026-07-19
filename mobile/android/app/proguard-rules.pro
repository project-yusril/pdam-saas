-keepattributes *Annotation*
-keepattributes Signature
-keepattributes Exceptions
-keepattributes InnerClasses
-keepattributes EnclosingMethod
-keepattributes SourceFile,LineNumberTable

-keep public class * { public protected *; }
-keepclassmembers class * { @com.google.gson.annotations.SerializedName <fields>; }
-keepclassmembers class * { @com.squareup.moshi.* <fields>; }

-keep class com.google.gson.** { *; }
-keep class com.google.protobuf.** { *; }

-keep class io.flutter.app.** { *; }
-keep class io.flutter.plugin.** { *; }
-keep class io.flutter.util.** { *; }
-keep class io.flutter.view.** { *; }
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }

-keep class androidx.lifecycle.** { *; }
-dontwarn androidx.lifecycle.**

-keep class * extends androidx.room.RoomDatabase
-keep class * extends androidx.room.Entity
-keep class * extends androidx.room.Dao

-keepclassmembers class * {
    @retrofit2.http.* <methods>;
}

-keepclassmembers class * {
    @com.squareup.moshi.* <methods>;
}

-keepattributes RuntimeVisibleAnnotations
-keepattributes RuntimeInvisibleAnnotations
-keepattributes RuntimeVisibleParameterAnnotations
-keepattributes RuntimeInvisibleParameterAnnotations

-keepclassmembers enum * {
    public static **[] values();
    public static ** valueOf(java.lang.String);
}

-keepclassmembers class * implements android.os.Parcelable {
    public static final android.os.Parcelable$Creator CREATOR;
}

-keepclassmembers class **.R$* {
    public static <fields>;
}

-keep class kotlinx.coroutines.** { *; }
-dontwarn kotlinx.coroutines.**

-keep class com.google.android.gms.** { *; }
-dontwarn com.google.android.gms.**

-keep class com.google.firebase.** { *; }
-dontwarn com.google.firebase.**

-keep class id.go.pdam.mobile.** { *; }

-renamesourcefileattribute SourceFile
