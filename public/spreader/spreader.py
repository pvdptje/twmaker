import bpy
import math
from mathutils import Vector

# ============================================================
# CONFIG
# ============================================================

EXPORT_PATH = "//spreader.glb"

FRAME_RETRACTED = 1
FRAME_EXTENDED = 70
FRAME_LOCKED = 100
FRAME_STOWED = 140

# ============================================================
# CLEAR SCENE
# ============================================================

bpy.ops.object.select_all(action='SELECT')
bpy.ops.object.delete()

# ============================================================
# MATERIALS
# ============================================================

def create_mat(name, color, metallic=0.25, roughness=0.45):
    mat = bpy.data.materials.new(name)
    mat.use_nodes = True

    bsdf = mat.node_tree.nodes.get("Principled BSDF")
    bsdf.inputs["Base Color"].default_value = (*color, 1)
    bsdf.inputs["Metallic"].default_value = metallic
    bsdf.inputs["Roughness"].default_value = roughness

    return mat

mat_yellow = create_mat("stinis_yellow", (0.95, 0.72, 0.12), 0.35, 0.38)
mat_red    = create_mat("safety_red", (0.75, 0.04, 0.03), 0.25, 0.45)
mat_gray   = create_mat("steel_gray", (0.38, 0.38, 0.40), 0.55, 0.35)
mat_dark   = create_mat("dark_metal", (0.08, 0.08, 0.09), 0.7, 0.3)
mat_blue   = create_mat("stinis_blue", (0.02, 0.12, 0.45), 0.2, 0.5)

# ============================================================
# HELPERS
# ============================================================

def add_cube(name, loc, scale, mat=None, bevel=True):
    bpy.ops.mesh.primitive_cube_add(size=1, location=loc)
    obj = bpy.context.object
    obj.name = name
    obj.scale = scale

    if mat:
        obj.data.materials.append(mat)

    if bevel:
        bevel_mod = obj.modifiers.new("soft_bevel", "BEVEL")
        bevel_mod.width = 0.06
        bevel_mod.segments = 2

        weighted_normals = obj.modifiers.new("weighted_normals", "WEIGHTED_NORMAL")

    return obj


def add_cylinder(name, loc, radius, depth, mat=None, rotation=(0, 0, 0), vertices=32):
    bpy.ops.mesh.primitive_cylinder_add(
        vertices=vertices,
        radius=radius,
        depth=depth,
        location=loc,
        rotation=rotation
    )
    obj = bpy.context.object
    obj.name = name

    if mat:
        obj.data.materials.append(mat)

    bevel_mod = obj.modifiers.new("soft_bevel", "BEVEL")
    bevel_mod.width = 0.025
    bevel_mod.segments = 2

    obj.modifiers.new("weighted_normals", "WEIGHTED_NORMAL")

    return obj


def add_empty(name, loc):
    empty = bpy.data.objects.new(name, None)
    bpy.context.collection.objects.link(empty)
    empty.empty_display_type = "ARROWS"
    empty.empty_display_size = 1.0
    empty.location = loc
    return empty


def parent_keep_transform(child, parent):
    child.parent = parent
    child.matrix_parent_inverse = parent.matrix_world.inverted()


# ============================================================
# ROOT / ANIMATION GROUPS
# ============================================================

root = add_empty("ROOT_spreader", (0, 0, 0))

left_slide_pivot = add_empty("ANIM_left_telescopic_slide", (-5.5, 0, 2.1))
right_slide_pivot = add_empty("ANIM_right_telescopic_slide", (5.5, 0, 2.1))

left_leg_pivot = add_empty("ANIM_left_landing_leg", (-12.8, 0, 0.8))
right_leg_pivot = add_empty("ANIM_right_landing_leg", (12.8, 0, 0.8))

parent_keep_transform(left_slide_pivot, root)
parent_keep_transform(right_slide_pivot, root)
parent_keep_transform(left_leg_pivot, left_slide_pivot)
parent_keep_transform(right_leg_pivot, right_slide_pivot)

twistlock_pivots = []
hydraulic_rods = []

# ============================================================
# MAIN FRAME
# ============================================================

main_beam = add_cube(
    "main_central_beam",
    (0, 0, 2.2),
    (6.6, 1.05, 0.42),
    mat_yellow
)
parent_keep_transform(main_beam, root)

top_plate = add_cube(
    "top_equipment_platform",
    (0, 0, 2.78),
    (2.65, 1.16, 0.18),
    mat_yellow
)
parent_keep_transform(top_plate, root)

# angled-ish side housings
for side in [-1, 1]:
    housing = add_cube(
        f"{'left' if side < 0 else 'right'}_upper_side_housing",
        (side * 3.9, 0, 2.75),
        (1.45, 1.0, 0.45),
        mat_yellow
    )
    parent_keep_transform(housing, root)

# ============================================================
# TELESCOPIC ARMS
# ============================================================

for side, pivot in [(-1, left_slide_pivot), (1, right_slide_pivot)]:
    side_name = "left" if side < 0 else "right"

    outer = add_cube(
        f"{side_name}_fixed_outer_arm",
        (side * 5.4, 0, 2.1),
        (2.7, 0.92, 0.36),
        mat_yellow
    )
    parent_keep_transform(outer, root)

    inner = add_cube(
        f"{side_name}_sliding_inner_arm",
        (side * 8.9, 0, 2.05),
        (3.1, 0.78, 0.31),
        mat_yellow
    )
    parent_keep_transform(inner, pivot)

    end_head = add_cube(
        f"{side_name}_end_head_frame",
        (side * 12.2, 0, 2.0),
        (0.55, 1.25, 0.7),
        mat_yellow
    )
    parent_keep_transform(end_head, pivot)

    # vertical plates on end head
    for y in [-0.62, 0.62]:
        plate = add_cube(
            f"{side_name}_end_side_plate_{y}",
            (side * 12.2, y, 2.0),
            (0.42, 0.08, 0.85),
            mat_yellow
        )
        parent_keep_transform(plate, pivot)

    # hinge pins
    for y in [-0.48, 0.48]:
        pin = add_cylinder(
            f"{side_name}_hinge_pin_{y}",
            (side * 11.7, y, 2.0),
            0.13,
            0.95,
            mat_dark,
            rotation=(math.radians(90), 0, 0)
        )
        parent_keep_transform(pin, pivot)

# ============================================================
# LANDING LEGS / RED FEET
# ============================================================

for side, pivot in [(-1, left_leg_pivot), (1, right_leg_pivot)]:
    side_name = "left" if side < 0 else "right"

    leg_upper = add_cube(
        f"{side_name}_yellow_leg_mount",
        (side * 12.7, 0, 1.85),
        (0.48, 0.58, 0.72),
        mat_yellow
    )
    parent_keep_transform(leg_upper, pivot)

    red_leg = add_cube(
        f"{side_name}_red_landing_leg",
        (side * 12.8, 0, 0.8),
        (0.32, 0.42, 0.95),
        mat_red
    )
    parent_keep_transform(red_leg, pivot)

    foot = add_cube(
        f"{side_name}_red_foot_plate",
        (side * 12.8, 0, 0.13),
        (0.92, 0.58, 0.08),
        mat_red
    )
    parent_keep_transform(foot, pivot)

# ============================================================
# CENTER CONTROL BOX
# ============================================================

control_box = add_cube(
    "center_control_box_gray",
    (0, 0, 3.45),
    (1.18, 0.92, 0.62),
    mat_gray
)
parent_keep_transform(control_box, root)

control_roof = add_cube(
    "center_control_box_sloped_roof",
    (0, 0, 4.04),
    (1.32, 1.02, 0.12),
    mat_gray
)
control_roof.rotation_euler[1] = math.radians(-5)
parent_keep_transform(control_roof, root)

# railing behind box
for x in [-1.55, -1.2]:
    post = add_cylinder(
        f"rear_railing_post_{x}",
        (x, 1.24, 3.55),
        0.035,
        0.75,
        mat_yellow
    )
    parent_keep_transform(post, root)

rail = add_cylinder(
    "rear_railing_horizontal",
    (-1.38, 1.24, 3.95),
    0.035,
    0.75,
    mat_yellow,
    rotation=(0, math.radians(90), 0)
)
parent_keep_transform(rail, root)

# ============================================================
# HYDRAULIC CYLINDERS
# ============================================================

for side in [-1, 1]:
    side_name = "left" if side < 0 else "right"
    slide_pivot = left_slide_pivot if side < 0 else right_slide_pivot

    cyl_outer = add_cylinder(
        f"{side_name}_hydraulic_outer_body",
        (side * 5.7, -0.72, 2.62),
        0.12,
        2.6,
        mat_dark,
        rotation=(0, math.radians(82), 0)
    )
    parent_keep_transform(cyl_outer, root)

    rod_pivot = add_empty(f"ANIM_{side_name}_hydraulic_rod", (side * 7.4, -0.72, 2.58))
    parent_keep_transform(rod_pivot, slide_pivot)
    hydraulic_rods.append(rod_pivot)

    cyl_rod = add_cylinder(
        f"{side_name}_hydraulic_chrome_rod",
        (side * 7.4, -0.72, 2.58),
        0.07,
        2.5,
        mat_gray,
        rotation=(0, math.radians(82), 0)
    )
    parent_keep_transform(cyl_rod, rod_pivot)

# ============================================================
# TWISTLOCKS / LOCKING POINTS
# ============================================================

twist_positions = [
    (-12.25, -0.74, 1.15),
    (-12.25,  0.74, 1.15),
    ( 12.25, -0.74, 1.15),
    ( 12.25,  0.74, 1.15),
]

for i, pos in enumerate(twist_positions):
    side_name = "left" if pos[0] < 0 else "right"
    slide_pivot = left_slide_pivot if pos[0] < 0 else right_slide_pivot

    housing = add_cube(
        f"twistlock_housing_{i+1}",
        pos,
        (0.28, 0.26, 0.22),
        mat_gray
    )
    parent_keep_transform(housing, slide_pivot)

    lock_pivot = add_empty(f"ANIM_twistlock_pin_{i+1}_{side_name}", (pos[0], pos[1], pos[2] + 0.28))
    parent_keep_transform(lock_pivot, slide_pivot)
    twistlock_pivots.append(lock_pivot)

    lock = add_cylinder(
        f"twistlock_pin_{i+1}",
        (pos[0], pos[1], pos[2] + 0.28),
        0.12,
        0.42,
        mat_dark,
        rotation=(0, 0, math.radians(45)),
        vertices=24
    )
    parent_keep_transform(lock, lock_pivot)

# ============================================================
# SIDE DETAIL PLATES / BOLTS
# ============================================================

for side in [-1, 1]:
    side_name = "left" if side < 0 else "right"

    for x in [side * 4.3, side * 5.0, side * 5.7]:
        for z in [2.45, 1.92]:
            bolt = add_cylinder(
                f"{side_name}_side_bolt_{x}_{z}",
                (x, -1.08, z),
                0.06,
                0.05,
                mat_dark,
                rotation=(math.radians(90), 0, 0),
                vertices=16
            )
            parent_keep_transform(bolt, root)

# ============================================================
# SIMPLE STINIS TEXT
# ============================================================

bpy.ops.object.text_add(location=(-1.25, -1.115, 2.38), rotation=(math.radians(90), 0, 0))
text = bpy.context.object
text.name = "stinis_logo_text"
text.data.body = "stinis"
text.data.align_x = "CENTER"
text.data.align_y = "CENTER"
text.data.size = 0.65
text.data.extrude = 0.012
text.data.materials.append(mat_blue)
parent_keep_transform(text, root)

# ============================================================
# SAMPLE ANIMATION
# Exported as real GLB animation data and also useful as named
# pivots for Three.js scroll-driven motion.
# ============================================================

bpy.context.scene.frame_start = FRAME_RETRACTED
bpy.context.scene.frame_end = FRAME_STOWED
bpy.context.scene.render.fps = 24

left_slide_start = left_slide_pivot.location.copy()
right_slide_start = right_slide_pivot.location.copy()
left_leg_start = left_leg_pivot.location.copy()
right_leg_start = right_leg_pivot.location.copy()
rod_starts = {rod: rod.location.copy() for rod in hydraulic_rods}


def key_location(obj, frame, location):
    obj.location = location
    obj.keyframe_insert(data_path="location", frame=frame)


def key_rotation(obj, frame, rotation):
    obj.rotation_euler = rotation
    obj.keyframe_insert(data_path="rotation_euler", frame=frame)


def make_interpolation_linear(objects):
    for obj in objects:
        if obj.animation_data and obj.animation_data.action:
            for fcurve in obj.animation_data.action.fcurves:
                for keyframe in fcurve.keyframe_points:
                    keyframe.interpolation = "LINEAR"


# Stage 1: telescope both end sections outward.
key_location(left_slide_pivot, FRAME_RETRACTED, left_slide_start)
key_location(right_slide_pivot, FRAME_RETRACTED, right_slide_start)
key_location(left_slide_pivot, FRAME_EXTENDED, left_slide_start + Vector((-1.45, 0, 0)))
key_location(right_slide_pivot, FRAME_EXTENDED, right_slide_start + Vector((1.45, 0, 0)))
key_location(left_slide_pivot, FRAME_STOWED, left_slide_start)
key_location(right_slide_pivot, FRAME_STOWED, right_slide_start)

# Stage 2: show the chrome rods following the extension.
for rod in hydraulic_rods:
    side = -1 if "left" in rod.name else 1
    start = rod_starts[rod]
    key_location(rod, FRAME_RETRACTED, start)
    key_location(rod, FRAME_EXTENDED, start + Vector((side * 0.35, 0, 0)))
    key_location(rod, FRAME_STOWED, start)

# Stage 3: lower the landing legs after the arms are out.
key_location(left_leg_pivot, FRAME_RETRACTED, left_leg_start + Vector((0, 0, 0.45)))
key_location(right_leg_pivot, FRAME_RETRACTED, right_leg_start + Vector((0, 0, 0.45)))
key_location(left_leg_pivot, FRAME_EXTENDED, left_leg_start + Vector((0, 0, 0.45)))
key_location(right_leg_pivot, FRAME_EXTENDED, right_leg_start + Vector((0, 0, 0.45)))
key_location(left_leg_pivot, FRAME_LOCKED, left_leg_start)
key_location(right_leg_pivot, FRAME_LOCKED, right_leg_start)
key_location(left_leg_pivot, FRAME_STOWED, left_leg_start + Vector((0, 0, 0.45)))
key_location(right_leg_pivot, FRAME_STOWED, right_leg_start + Vector((0, 0, 0.45)))

# Stage 4: rotate twistlock pins into the locked position.
for i, lock_pivot in enumerate(twistlock_pivots):
    direction = -1 if i < 2 else 1
    start_rotation = lock_pivot.rotation_euler.copy()
    locked_rotation = start_rotation.copy()
    locked_rotation.z += math.radians(90) * direction

    key_rotation(lock_pivot, FRAME_RETRACTED, start_rotation)
    key_rotation(lock_pivot, FRAME_EXTENDED, start_rotation)
    key_rotation(lock_pivot, FRAME_LOCKED, locked_rotation)
    key_rotation(lock_pivot, FRAME_STOWED, start_rotation)

make_interpolation_linear([
    left_slide_pivot,
    right_slide_pivot,
    left_leg_pivot,
    right_leg_pivot,
    *hydraulic_rods,
    *twistlock_pivots
])

bpy.context.scene.frame_set(FRAME_RETRACTED)

# ============================================================
# CAMERA / LIGHTS
# ============================================================

bpy.ops.object.light_add(type="AREA", location=(0, -6, 7))
light = bpy.context.object
light.name = "large_softbox"
light.data.energy = 700
light.data.size = 5

bpy.ops.object.camera_add(location=(8, -9, 5), rotation=(math.radians(60), 0, math.radians(42)))
camera = bpy.context.object
bpy.context.scene.camera = camera

# ============================================================
# APPLY MODIFIERS BEFORE EXPORT
# ============================================================

for obj in bpy.context.scene.objects:
    if obj.type == "MESH":
        bpy.context.view_layer.objects.active = obj
        obj.select_set(True)

        for modifier in obj.modifiers:
            try:
                bpy.ops.object.modifier_apply(modifier=modifier.name)
            except Exception:
                pass

        obj.select_set(False)

# ============================================================
# EXPORT GLB
# ============================================================

bpy.ops.export_scene.gltf(
    filepath=EXPORT_PATH,
    export_format="GLB",
    export_apply=False,
    export_animations=True,
    export_force_sampling=True,
    export_yup=True,
    export_lights=False,
    export_cameras=False
)

print("Stinis spreader GLB exported:")
print(EXPORT_PATH)
