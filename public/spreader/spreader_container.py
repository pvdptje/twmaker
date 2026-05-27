import bpy
import math
from mathutils import Vector

# ============================================================
# CONFIG
# ============================================================

EXPORT_PATH = "//spreader_container.glb"

FRAME_START = 1
FRAME_DESCEND = 45
FRAME_LOCK = 70
FRAME_LIFT = 130
FRAME_RETURN = 170

# ============================================================
# CLEAR SCENE
# ============================================================

bpy.ops.object.select_all(action="SELECT")
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
mat_red = create_mat("safety_red", (0.75, 0.04, 0.03), 0.25, 0.45)
mat_gray = create_mat("steel_gray", (0.42, 0.42, 0.44), 0.55, 0.35)
mat_dark = create_mat("dark_metal", (0.08, 0.08, 0.09), 0.7, 0.3)
mat_blue = create_mat("stinis_blue", (0.02, 0.12, 0.45), 0.2, 0.5)
mat_container = create_mat("container_blue", (0.05, 0.26, 0.62), 0.2, 0.55)
mat_container_dark = create_mat("container_shadow_blue", (0.03, 0.14, 0.32), 0.25, 0.62)

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
        bevel_mod.width = 0.035
        bevel_mod.segments = 2
        obj.modifiers.new("weighted_normals", "WEIGHTED_NORMAL")

    return obj


def add_cylinder(name, loc, radius, depth, mat=None, rotation=(0, 0, 0), vertices=32):
    bpy.ops.mesh.primitive_cylinder_add(
        vertices=vertices,
        radius=radius,
        depth=depth,
        location=loc,
        rotation=rotation,
    )
    obj = bpy.context.object
    obj.name = name

    if mat:
        obj.data.materials.append(mat)

    bevel_mod = obj.modifiers.new("soft_bevel", "BEVEL")
    bevel_mod.width = 0.02
    bevel_mod.segments = 2
    obj.modifiers.new("weighted_normals", "WEIGHTED_NORMAL")

    return obj


def add_empty(name, loc):
    empty = bpy.data.objects.new(name, None)
    bpy.context.collection.objects.link(empty)
    empty.empty_display_type = "ARROWS"
    empty.empty_display_size = 0.9
    empty.location = loc
    return empty


def parent_keep_transform(child, parent):
    child.parent = parent
    child.matrix_parent_inverse = parent.matrix_world.inverted()


def key_location(obj, frame, location):
    obj.location = location
    obj.keyframe_insert(data_path="location", frame=frame)


def key_rotation(obj, frame, rotation):
    obj.rotation_euler = rotation
    obj.keyframe_insert(data_path="rotation_euler", frame=frame)


def set_smooth_interpolation(objects):
    for obj in objects:
        if obj.animation_data and obj.animation_data.action:
            for fcurve in obj.animation_data.action.fcurves:
                for keyframe in fcurve.keyframe_points:
                    keyframe.interpolation = "BEZIER"


# ============================================================
# ROOTS / ANIMATION GROUPS
# ============================================================

spreader_root = add_empty("ANIM_spreader_lift_head", (0, 0, 4.15))
container_root = add_empty("ANIM_container_payload", (0, 0, 0))
left_slide_pivot = add_empty("ANIM_left_telescopic_slide", (-5.5, 0, 0))
right_slide_pivot = add_empty("ANIM_right_telescopic_slide", (5.5, 0, 0))

parent_keep_transform(left_slide_pivot, spreader_root)
parent_keep_transform(right_slide_pivot, spreader_root)

twistlock_pivots = []

# ============================================================
# SPREADER
# ============================================================

main_beam = add_cube("main_central_beam", (0, 0, 4.15), (6.8, 0.95, 0.35), mat_yellow)
parent_keep_transform(main_beam, spreader_root)

top_platform = add_cube("top_equipment_platform", (0, 0, 4.63), (2.4, 1.05, 0.14), mat_yellow)
parent_keep_transform(top_platform, spreader_root)

control_box = add_cube("center_control_box", (0, 0, 5.08), (1.05, 0.82, 0.46), mat_gray)
parent_keep_transform(control_box, spreader_root)

for side, pivot in [(-1, left_slide_pivot), (1, right_slide_pivot)]:
    side_name = "left" if side < 0 else "right"

    outer = add_cube(
        f"{side_name}_fixed_outer_arm",
        (side * 4.1, 0, 4.08),
        (1.6, 0.78, 0.28),
        mat_yellow,
    )
    parent_keep_transform(outer, spreader_root)

    inner = add_cube(
        f"{side_name}_sliding_inner_arm",
        (side * 7.4, 0, 4.03),
        (3.2, 0.68, 0.24),
        mat_yellow,
    )
    parent_keep_transform(inner, pivot)

    head = add_cube(
        f"{side_name}_end_head",
        (side * 10.15, 0, 3.96),
        (0.42, 1.12, 0.62),
        mat_yellow,
    )
    parent_keep_transform(head, pivot)

    for y in [-0.54, 0.54]:
        cheek = add_cube(
            f"{side_name}_end_cheek_plate_{y}",
            (side * 10.15, y, 3.96),
            (0.34, 0.08, 0.72),
            mat_yellow,
        )
        parent_keep_transform(cheek, pivot)

    rod = add_cylinder(
        f"ANIM_{side_name}_hydraulic_rod",
        (side * 6.25, -0.62, 4.42),
        0.07,
        2.35,
        mat_gray,
        rotation=(0, math.radians(82), 0),
    )
    parent_keep_transform(rod, pivot)

    body = add_cylinder(
        f"{side_name}_hydraulic_body",
        (side * 4.95, -0.62, 4.48),
        0.12,
        1.9,
        mat_dark,
        rotation=(0, math.radians(82), 0),
    )
    parent_keep_transform(body, spreader_root)

# Lift hooks and twistlocks line up with the container corners.
corner_positions = [
    (-10.15, -1.08, 3.42),
    (-10.15, 1.08, 3.42),
    (10.15, -1.08, 3.42),
    (10.15, 1.08, 3.42),
]

for index, pos in enumerate(corner_positions, start=1):
    side_name = "left" if pos[0] < 0 else "right"
    parent = left_slide_pivot if pos[0] < 0 else right_slide_pivot

    guide = add_cube(f"lock_guide_{index}", pos, (0.22, 0.22, 0.16), mat_gray)
    parent_keep_transform(guide, parent)

    lock_pivot = add_empty(f"ANIM_twistlock_pin_{index}_{side_name}", (pos[0], pos[1], pos[2] - 0.24))
    parent_keep_transform(lock_pivot, parent)
    twistlock_pivots.append(lock_pivot)

    pin = add_cylinder(
        f"twistlock_pin_{index}",
        (pos[0], pos[1], pos[2] - 0.24),
        0.1,
        0.42,
        mat_dark,
        rotation=(0, 0, math.radians(45)),
        vertices=24,
    )
    parent_keep_transform(pin, lock_pivot)

for x in [-0.35, 0.35]:
    post = add_cylinder(f"yellow_lift_eye_{x}", (x, 0, 5.38), 0.035, 0.85, mat_yellow)
    parent_keep_transform(post, spreader_root)

bridge = add_cylinder(
    "yellow_lift_eye_bridge",
    (0, 0, 5.82),
    0.04,
    0.85,
    mat_yellow,
    rotation=(0, math.radians(90), 0),
)
parent_keep_transform(bridge, spreader_root)

bpy.ops.object.text_add(location=(-1.05, -1.03, 4.28), rotation=(math.radians(90), 0, 0))
logo = bpy.context.object
logo.name = "stinis_logo_text"
logo.data.body = "stinis"
logo.data.align_x = "CENTER"
logo.data.align_y = "CENTER"
logo.data.size = 0.52
logo.data.extrude = 0.01
logo.data.materials.append(mat_blue)
parent_keep_transform(logo, spreader_root)

# ============================================================
# CONTAINER
# ============================================================

container_body = add_cube("container_40ft_body", (0, 0, 1.55), (10.2, 1.25, 1.35), mat_container)
parent_keep_transform(container_body, container_root)

roof = add_cube("container_roof_highlight", (0, 0, 2.94), (10.25, 1.28, 0.04), mat_container_dark)
parent_keep_transform(roof, container_root)

for x in [-10.15, 10.15]:
    for y in [-1.08, 1.08]:
        corner = add_cube(
            f"container_corner_casting_{x}_{y}",
            (x, y, 3.02),
            (0.3, 0.28, 0.18),
            mat_dark,
        )
        parent_keep_transform(corner, container_root)

for x in [-8.6, -6.9, -5.2, -3.5, -1.8, -0.1, 1.6, 3.3, 5.0, 6.7, 8.4]:
    rib_front = add_cube(f"front_container_rib_{x}", (x, -1.285, 1.55), (0.045, 0.035, 1.28), mat_container_dark)
    rib_back = add_cube(f"rear_container_rib_{x}", (x, 1.285, 1.55), (0.045, 0.035, 1.28), mat_container_dark)
    parent_keep_transform(rib_front, container_root)
    parent_keep_transform(rib_back, container_root)

for x in [-9.5, 9.5]:
    door = add_cube(f"container_end_door_{x}", (x, -1.31, 1.5), (0.42, 0.035, 1.12), mat_container_dark)
    parent_keep_transform(door, container_root)

# ============================================================
# ANIMATION
# ============================================================

bpy.context.scene.frame_start = FRAME_START
bpy.context.scene.frame_end = FRAME_RETURN
bpy.context.scene.render.fps = 24

spreader_start = spreader_root.location.copy()
container_start = container_root.location.copy()
left_slide_start = left_slide_pivot.location.copy()
right_slide_start = right_slide_pivot.location.copy()

key_location(spreader_root, FRAME_START, spreader_start)
key_location(container_root, FRAME_START, container_start)
key_location(left_slide_pivot, FRAME_START, left_slide_start)
key_location(right_slide_pivot, FRAME_START, right_slide_start)

# Extend to match the 40 ft corner castings.
key_location(left_slide_pivot, FRAME_DESCEND, left_slide_start + Vector((-0.9, 0, 0)))
key_location(right_slide_pivot, FRAME_DESCEND, right_slide_start + Vector((0.9, 0, 0)))

# Lower the spreader onto the container.
key_location(spreader_root, FRAME_DESCEND, spreader_start + Vector((0, 0, -0.9)))
key_location(container_root, FRAME_DESCEND, container_start)

# Twistlocks rotate before the lift begins.
for i, lock_pivot in enumerate(twistlock_pivots):
    direction = -1 if i < 2 else 1
    start_rotation = lock_pivot.rotation_euler.copy()
    locked_rotation = start_rotation.copy()
    locked_rotation.z += math.radians(90) * direction

    key_rotation(lock_pivot, FRAME_START, start_rotation)
    key_rotation(lock_pivot, FRAME_DESCEND, start_rotation)
    key_rotation(lock_pivot, FRAME_LOCK, locked_rotation)
    key_rotation(lock_pivot, FRAME_LIFT, locked_rotation)
    key_rotation(lock_pivot, FRAME_RETURN, start_rotation)

key_location(spreader_root, FRAME_LOCK, spreader_start + Vector((0, 0, -0.9)))
key_location(container_root, FRAME_LOCK, container_start)
key_location(left_slide_pivot, FRAME_LOCK, left_slide_start + Vector((-0.9, 0, 0)))
key_location(right_slide_pivot, FRAME_LOCK, right_slide_start + Vector((0.9, 0, 0)))

# Lift spreader and container together.
lift_offset = Vector((0, 0, 1.75))
key_location(spreader_root, FRAME_LIFT, spreader_start + lift_offset)
key_location(container_root, FRAME_LIFT, container_start + lift_offset)
key_location(left_slide_pivot, FRAME_LIFT, left_slide_start + Vector((-0.9, 0, 0)))
key_location(right_slide_pivot, FRAME_LIFT, right_slide_start + Vector((0.9, 0, 0)))

# Return to start so the exported clip loops cleanly.
key_location(spreader_root, FRAME_RETURN, spreader_start)
key_location(container_root, FRAME_RETURN, container_start)
key_location(left_slide_pivot, FRAME_RETURN, left_slide_start)
key_location(right_slide_pivot, FRAME_RETURN, right_slide_start)

set_smooth_interpolation([
    spreader_root,
    container_root,
    left_slide_pivot,
    right_slide_pivot,
    *twistlock_pivots,
])

bpy.context.scene.frame_set(FRAME_START)

# ============================================================
# CAMERA / LIGHTS
# ============================================================

bpy.ops.object.light_add(type="AREA", location=(0, -7, 8))
light = bpy.context.object
light.name = "large_softbox"
light.data.energy = 800
light.data.size = 5

bpy.ops.object.camera_add(location=(8.8, -8.6, 5.4), rotation=(math.radians(60), 0, math.radians(44)))
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
    export_cameras=False,
)

print("Spreader container lift GLB exported:")
print(EXPORT_PATH)
